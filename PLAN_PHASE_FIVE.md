# TIA deep audit — phase five

## Your task

Phases one through four fixed the defects that were *reported*. This phase is the opposite shape: go
looking. Read TIA's read/write path adversarially, find what is wrong or fragile, and leave behind a
scenario suite that covers the edges nobody has exercised yet.

Two deliverables, both required:

1. **A ranked findings list.** Every finding needs a *reproduction*, not a reading of the code. A
   finding you cannot reproduce is a hypothesis — say so and rank it separately.
2. **New scenario tests** in `tests/Features/Tia/*`, covering the edges you probed. Rows that pass go
   in the repo (they are the regression net). Rows that fail stay in your scratchpad until the fix
   lands — **never commit a red test.**

Work in passes, and **report between passes** rather than at the very end:

- **Pass A** — reproduce the leads in Part 3 below. Report which are real.
- **Pass B** — your own hunt: the invariants in Part 2, attacked with inputs nobody tried.
- **Pass C** — fixes, smallest first, each with the row that pins it.

Fix what is clearly a defect with an obvious correct answer. **Stop and ask** when the fix is a
behaviour *choice* (what should TIA do when two runs race for one graph?) — those are Nuno's calls,
and a reproduction with a crisp yes/no question is worth more than a guessed fix.

---

## Part 1 — The harness

### 1.1 What TIA is

Test Impact Analysis. `pest --tia` records a dependency graph (test file → source files it touched)
plus a per-branch baseline of results, then on later runs replays the tests whose dependencies did not
change. State lives in a per-project dir under `~/.pest/tia/`; `graph.json` is the whole thing.

Source of truth: `src/Plugins/Tia.php` (the plugin, ~1900 lines) and `src/Plugins/Tia/Graph.php` (the
graph model + read/write API). Supporting: `src/Plugins/Tia/ChangedFiles.php` (git), `Fingerprint.php`
(environment/structure hashing), `State.php` (the state dir).

Read `PLAN.md`, `PLAN_PHASE_TWO.md`, `PLAN_PHASE_THREE.md`, `PLAN_PHASE_FOUR.md` first — in that
order. They carry the history, the tier contract, and the decisions already made. Struck-through rows
are fixed; do not re-report them.

### 1.2 The scenario harness

`tests/Features/Tia/*` scaffold a throwaway git project into a temp dir, run a **real `pest`
subprocess** against it, and diff the graph it wrote. Everything lives in `tests/Fixtures/Tia/`:

| Class | What it gives you |
|---|---|
| `Project` | `make(branch, overlay:)`, `withoutGit()`, `seed(branch, sentinel:, failing:)`, `seedFor($root, …)`, `pest(...$args)`, `pestWithEnvironment($dir, $env, ...$args)`, `pestIn($dir, ...)`, `write($rel, $contents)`, `path($rel)`, `graph()`, `branchKeys()`, `graphDir()`, `graphExists()`, `snapshot()`, `delta()`, `mutateGraph(fn)`, `addBaseline($branch)`, `worktree($branch)`, `destroy()`, `destroyAll()`, `SEQUENTIAL_AND_PARALLEL` |
| `GitRepo` (`$project->git()`) | `switchTo($b, new:)`, `rename($from, $to)`, `detach()`, `config($k, $v)`, `addOrigin()`, `removeOrigin()`, `setOriginHead($b)`, `unsetOriginHead()`, `worktree()`, `sha()`, `branchNames()` |
| `GraphDelta` (`$project->delta()`) | `writtenCount()`, `added()`, `removed()`, `branchKeys()`, `baselineUntouched($b)`, `shaMoved()`, `treeMoved()`, `edgesMoved()`, `filesMoved()`, `fingerprintMoved()`, `structureMoved()`, `isResultsOnly()`, `isHardSuppressed()`, `summary()` |
| `PestResult` (returned by `pest()`) | `replayed()`, `uncached()`, `affected()`, `tally()`, `output`, `exitCode`, `describe()` |

The fixture app is 3 test files / **6 tests** (`Project::TOTAL_TESTS`); `Project::EDGES` and
`Project::TESTS` describe the graph `seed()` writes. `Project::testId($file, $description)` builds a
result key. Overlays in `tests/Fixtures/Tia/overlays/<name>/` supply a different `tests/Pest.php` —
that is how you configure `pest()->tia()->…` for a scenario.

**If the fixture cannot express your case, extend the fixture.** A new `GitRepo` verb or a new overlay
is a legitimate part of the deliverable — several findings below need one. Do not water down a
scenario to fit the current helpers.

### 1.3 The sentinel discriminator — read before writing any assertion

`seed()` writes a graph **and then rewrites every cached result to `time=9.999`, `assertions=42`**
(only where assertions are non-zero — risky/skipped/incomplete statuses are *derived* from "performed
no assertions", so falsifying those would rewrite the status on replay), then snapshots. Therefore:

- `$delta->writtenCount()` is the only reliable way to tell **"replayed"** from **"executed and wrote
  back the same values"**. `0` means nothing was written.
- `isResultsOnly()` = no structure moved, no `sha`/`tree` movement, nothing added or removed.
- `isHardSuppressed()` = the graph is byte-identical.
- A replayed suite reports inflated assertion counts (6 tests × 42). That is the sentinel, not a bug.

The three write tiers, unchanged since phase two:

- **COMPLETE** — may change everything.
- **RESULTS-ONLY** — may change only `baselines[<branch>].results` for tests that ran; never removes
  an entry, never adds a result for a test file absent from `edges`, never touches
  `sha`/`tree`/`edges`/`files`/`fingerprint`.
- **HARD-SUPPRESSED** — may change nothing.

### 1.4 Running them

They are in the `integration` group (`tests/Pest.php`), so `composer test:unit` skips them. **A
directory argument finds nothing** — pass files, space-separated, as separate argv entries:

```bash
PAO_DISABLE=1 php84 bin/pest tests/Features/Tia/DefaultBranchReplay.php tests/Features/Tia/DefaultBranchResolution.php \
  tests/Features/Tia/DefaultBranchWriteTier.php tests/Features/Tia/PartialRunWriteTier.php \
  tests/Features/Tia/CompleteRunWriteTier.php tests/Features/Tia/FilteredMode.php

PAO_DISABLE=1 php   bin/pest <same list>
```

**Both interpreters must be green.** `php84` is 8.4.x with **no pcov** — that is what CI has
(`.github/workflows/tests.yml` sets `coverage: none`). `php` is 8.5.x with pcov — that is the dev
machine. Any assertion that depends on a coverage driver passes locally and fails in CI. Concretely:

- A **cold recording run writes no graph at all** without pcov/xdebug (it prints `Running in TIA mode,
  however TIA is skipped as it needs ext-pcov or Xdebug`). **Seed a graph, never record one**, unless
  the driver *is* the point of the row.
- A **PHP source file edit** driverless triggers `Detected PHP source changes but no coverage driver
  is available` → full suite, `affected=0`. Edit *test* files, not `app/` files.

`PAO_DISABLE=1` is mandatory on every pest invocation and every probe: `laravel/pao` emits JSON under
agents and corrupts the captured output.

Baseline before you touch anything: **72 passed** on both interpreters, at `HEAD` plus the phase-four
working-tree changes. If that number does not reproduce, stop and say so.

### 1.5 Measure before you assert

Never guess an expectation from reading the code. Probe first, in your scratchpad:

```php
<?php
require '/Users/nunomaduro/Work/projects/pestphp/pest/vendor/autoload.php';
use Tests\Fixtures\Tia\Project;

$p = Project::make('master');
$p->seed('master');
$p->git()->switchTo('feature-x', new: true);
$r = $p->pest('--tia');
printf("tally=[%s] keys=[%s] %s\n", $r->tally(), implode(',', $p->branchKeys()), $p->delta()->summary());
$p->destroy();
```

`PAO_DISABLE=1 php probe.php`. One project per scenario; always `destroy()`; `Project::destroyAll()`
at the end. Run every probe on **both** interpreters before believing it.

### 1.6 Ground rules

- **Do not** run `composer test`. It takes minutes and you do not need it.
- **Do not** run `composer update:snapshots`. `tests/.snapshots/success.txt` and the tally in
  `tests/Visual/Parallel.php` encode the whole suite's result, so every row you add breaks them.
  That is expected — **report that they need regenerating and let Nuno run it.**
- **Do not commit.** Leave the tree dirty.
- **Do not touch the playground's `vendor/`.** If a finding truly needs the playground (25 real tests,
  real timings), say so and ask — syncing it is a manual step Nuno owns.
- Run `vendor/bin/phpstan analyse <the files you touched> --memory-limit=-1 --no-progress` and
  `vendor/bin/pint <the files you touched>` before reporting. **Scope both to files you changed** —
  Nuno edits `src/` live, and a repo-wide fixer will revert his work.
- The fixture projects **hardlink `src/`**. If you edit `src/` while a scenario run is in flight,
  those rows go red for no reason. Finish the edit, then run.
- Keep scratch probes out of the repo.
- **Never weaken an existing assertion to make something pass.** If an existing row contradicts your
  fix, that is a finding: report the contradiction and ask.

---

## Part 2 — The invariants to attack

These are the properties TIA is supposed to have. Each one is a place to hunt: construct the input
that breaks it.

1. **Parity.** `pest <args>` and `pest --parallel --processes=N <args>` must leave the **same graph**
   and reach the same tally. This is a hard rule from Nuno — sequential and parallel must *always*
   agree. `Project::SEQUENTIAL_AND_PARALLEL` is the dataset that encodes it; consider making every
   new write-path row use it. Vary `--processes` (1, 2, 8 — more processes than test files).
2. **The tiers hold.** Every command lands in exactly one of COMPLETE / RESULTS-ONLY /
   HARD-SUPPRESSED, and stays inside it. Combinations are where this frays: `--fresh --parallel
   --filter`, `--bail --shard`, `--filtered` plus an explicit path, `--tia --no-tia`, `--retry`.
3. **Replay is faithful.** A replayed test reports the same status, message, time and assertion count
   as the recorded run — and replay itself writes nothing. Statuses beyond pass/fail are the soft
   spot: skipped, incomplete, risky, notice, deprecation, warning, todo, and a test that failed with a
   multi-line message.
4. **Reads never write.** No read path may mint a baseline key, move a `sha`, or create the state dir.
   A project that has never run TIA must gain nothing from a plain `pest` run.
5. **A branch never corrupts another branch's baseline.** Writes land on the branch that ran, and
   only there. Reads may *layer* the default branch under the current one (phase four, B1) — but that
   layering must not leak into a write.
6. **Nothing is unbounded.** Baseline keys, `files`, `edges`, worker partials, state files: something
   must eventually reclaim them, or the graph grows forever.
7. **A hostile state dir cannot break a run.** Corrupt, truncated, empty, wrong-schema, read-only,
   absent, or *someone else's* `graph.json` — the suite still runs and exits on the tests' merit.
8. **Git shapes are all handled.** Detached HEAD (read-only, per phase four B2), worktrees, no commits
   yet, no `origin`, no `origin/HEAD`, submodules, a repo whose root is above the pest project (that
   one panics deliberately — `TiaRequiresRepositoryRoot`), renamed branches, deleted branches.

---

## Part 3 — Leads to reproduce first (Pass A)

These came out of reading the phase-four diff. **Each is a hypothesis, not a finding** — several may
turn out to be fine. Reproduce or refute each, in order, and report the measured delta either way.

### L1 — the per-entry fallback may resurrect a pruned or deleted test · **highest value**

Phase four made `Graph::baselineFor()` layer the branch's results **over** the default branch's. Two
consequences worth probing:

- `pruneStaleResults()` unsets an entry from `baselines[branch].results`. The very next read layers the
  **fallback's** entry for that same test id back in. So a delete may not stick from a branch's point
  of view.
- `hasUnlocatedTestsToRerun()` returns true when a *failing* cached result names a file that no longer
  exists on disk — and that forces a **full suite**. If a feature branch deletes a test file that
  fails on the default branch, the merged read still carries master's entry pointing at the now-absent
  file. Suspected symptom: **that branch runs the full suite forever.**

Probe: seed master with a failing test (`seed('master', failing: [...])`), branch off, delete the test
file that holds it, run `--tia`, and compare `replayed`/`uncached`/`affected` against the same shape
where the failure is on the branch instead. Then the mirror case with a green deleted test.

### L2 — environment drift clears one branch's results and the fallback serves them right back

`reconcileFingerprint()` on environmental drift calls `$graph->clearResults($this->branch)` and warns
`results dropped, edges reused`. On a feature branch that clears only the *branch's* results — the
layered read then re-serves the default branch's results, which were recorded under the **old**
environment. Suspected symptom: the drop is a no-op on any branch that is not the default one.

Probe: `pestWithEnvironment()` to shift whatever `Fingerprint` reads as environmental (check
`Fingerprint::environmentalDrift()` for the exact keys), on the default branch vs a feature branch,
and compare what survives.

### L3 — `sha`/`tree` may be taken from a different commit than the results

`baselineFor()` takes `sha` from the branch when non-null and otherwise from the fallback; `tree`
likewise when the branch's is empty. So a branch can end up computing "what changed since" against the
**default branch's** recorded sha while reading its own results — or vice versa. Is there a shape where
that under-reports changed files (a test replays that should have run)? That is the dangerous
direction: a false replay is a lie about a passing test.

Probe: seed master, commit a test edit on the branch so the shas genuinely differ, and check whether
the edited test is treated as affected.

### L4 — pruning from merged worker partials

Phase four made a complete `--parallel` run write and prune from merged worker results. The stated
safety net is that `pruneStaleResults()` only prunes files it saw results for, and that a truncated
worker sets results-only. Try to defeat it: a worker that reports results for a test file it did not
finish. Candidate shapes — a fatal error mid-file (not an assertion failure), `exit()` inside a test,
an uncaught error in an `afterEach`, a test that kills its own process, `--stop-on-failure` variants,
`--processes` greater than the number of test files.

### L5 — two runs racing for one `graph.json`

`State::write()` has no locking. Two pest processes on one project (a watcher plus a manual run, two CI
jobs sharing a cache dir, `--parallel` where the parent writes while a straggler worker flushes) can
lose an update or interleave. Probe by launching two `pest --tia` subprocesses concurrently against one
project and diffing. **This is likely a design decision, not a bug** — if you reproduce a lost update,
report it as a question (accept last-writer-wins, or lock?), do not invent a locking scheme.

### L6 — a detached HEAD still purges on structural drift

Phase four made a detached HEAD read-only *for writes* (`saveGraph()` refuses). But
`reconcileFingerprint()` deletes the whole graph on structural drift (`Tia.php`, the
`state->delete(KEY_GRAPH)` in the structural branch) before any write happens. So `--tia` from a
detached checkout with a changed `composer.lock` can still wipe the default branch's baseline. Confirm
it, then ask: should the detached-HEAD guard cover the purge too?

### L7 — statuses that may not round-trip

`PLAN.md` §5 claims warnings and deprecations record as `status=0`, and that codes `6` and `4` look
unreachable. `Graph::getResult()` maps 0–8 to `TestStatus`. Verify each status end to end: record it,
replay it, and check the replayed run reports the same thing — including the message, the exit code,
and whether `shouldRerunStatus()` decides to re-execute it. `failOnRisky` / `failOnSkipped` /
`displayDetailsOn*` change that decision, so an overlay that flips those config flags is part of this.
A status that replays as a pass would be the most serious class of bug in TIA.

### L8 — branch-key hygiene

Nothing appears to reclaim baseline keys. Probe: create and delete 5 branches, rename one
(`GitRepo::rename()`), and check what `branchKeys()` holds afterwards. Also try names that stress the
JSON keying: `feature/x/y` (already covered), a name differing from another only in case (macOS is
case-insensitive — does the key match the ref?), a name with a space or a unicode character, a branch
literally called `HEAD`, and a very long name. Then: is unbounded growth acceptable, or does this need
a cap / GC? Ask rather than build.

### L9 — `soleRecordedBranch()` as a fallback source

When config, CI env and git all fail to name a default branch, resolution falls back to "the only
branch in the graph". If that sole key was minted by a *narrowed* run on a feature branch (which phase
four's B1 made a live possibility), the fallback now names a feature branch, and every other branch
layers **its** results underneath. Probe: `withoutGit()` or `removeOrigin()` + no config, with a graph
whose only key is `feature-x`.

### L10 — the state dir as an adversary

Beyond corrupt JSON (fixed in phase four by deleting it): a valid-JSON graph with `schema: 2`; a graph
whose `files` and `edges` disagree; `results` entries with a `file` pointing outside the project root
or at an absolute path from another machine; a `graph.json` that is a directory; a state dir with no
write permission; `$HOME` unset. Each should degrade to "run the tests", never crash and never write
garbage.

---

## Part 4 — Reporting

**Between passes**, not just at the end. Per finding:

- **Reproduced (yes / no / hypothesis only)**, with the exact command and the measured
  `tally` + `delta()->summary()` on **both** interpreters.
- **Severity**, and say why in one line. The scale that matters here: *a test wrongly replayed as
  passing* (worst) > *cache silently useless, full suite forever* > *graph grows / stale data* >
  *cosmetic*.
- **Fixed / deferred / needs-a-decision**, and the row that pins it.
- For anything needing a decision: **one yes/no question**, no essay.

Close with:

1. The count of `tests/Features/Tia/*` green on **both** `php84` (no pcov) and `php85` (pcov), against
   the 72 baseline.
2. Every row you added, and what invariant from Part 2 it defends.
3. Which findings are still open, as yes/no questions.
4. That `tests/.snapshots/success.txt` and the `tests/Visual/Parallel.php` tally need regenerating —
   **do not regenerate them.**
5. **What you looked at and found solid.** A list of attacks that did not break anything is a real
   result: it tells the next phase where not to spend its time.
