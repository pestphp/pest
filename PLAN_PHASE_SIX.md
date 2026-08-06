# TIA deep audit — phase six

## Your task

Phase five went looking rather than fixing what was reported, and found nine defects in the
**read/write path and the state dir**. All nine are fixed and pinned by rows in the repo. It stopped
there deliberately: roughly half of TIA was never opened. Phase six is that other half.

Two deliverables, both required:

1. **A ranked findings list.** Every finding needs a *reproduction*, not a reading of the code. A
   finding you cannot reproduce is a hypothesis — say so and rank it separately.
2. **New scenario tests** in `tests/Features/Tia/*`. Rows that pass go in the repo (they are the
   regression net). Rows that fail stay in your scratchpad until the fix lands — **never commit a red
   test.**

Work in passes, and **report between passes** rather than at the very end:

- **Pass A** — reproduce the leads in Part 3. Report which are real.
- **Pass B** — your own hunt: the invariants in Part 2, attacked with inputs nobody tried.
- **Pass C** — fixes, smallest first, each with the row that pins it.

Fix what is clearly a defect with an obvious correct answer. **Stop and ask** when the fix is a
behaviour *choice* — those are Nuno's calls, and a reproduction with a crisp yes/no question is worth
more than a guessed fix.

---

## Part 1 — Where things stand

### 1.1 What TIA is

Test Impact Analysis. `pest --tia` records a dependency graph (test file → source files it touched)
plus a per-branch baseline of results, then on later runs replays the tests whose dependencies did not
change. State lives in a per-project dir under `~/.pest/tia/`; `graph.json` is the whole thing.

Source of truth: `src/Plugins/Tia.php` (the plugin) and `src/Plugins/Tia/Graph.php` (the graph model +
read/write API). Supporting: `ChangedFiles.php` (git), `Fingerprint.php` (environment/structure
hashing), `Storage.php` / `FileState.php` (the state dir), `ResultCollector.php` (what a run observed),
`Recorder.php` + `CoverageCollector.php` (how edges are recorded).

Read `PLAN.md`, then `PLAN_PHASE_TWO.md` → `THREE` → `FOUR` → `FIVE` for the history and the tier
contract. Struck-through rows are fixed; do not re-report them. **Section 1.2 below supersedes any
phase-five row that contradicts it.**

### 1.2 What phase five settled — do not re-report these

Nine defects, all fixed, each with a row that fails if it comes back:

| # | Defect | Fix | Pinned by |
|---|---|---|---|
| 1 | `pruneStaleResults()` unset an entry; the next read layered the default branch's entry for the same test id back in, so a renamed/removed test stayed "previously unsuccessful" on a feature branch forever | `Graph::baselineFor()` layers per *file* once a branch has had a complete run (new `complete` flag on the baseline); a key minted by a narrowed run keeps the per-test-id merge | `StateReclamation` → *a pruned result does not come back from the fallback*, *the fallback still reaches a branch that has never run a test file* |
| 2 | A cached failure whose test file was deleted was never reclaimed, so `--filtered` degraded to a full replay on every later run | `hasUnlocatedTestsToRerun()` widens only for a path it cannot *address*; `Graph::pruneResultsForMissingFiles()` + `pruneMissingTests()` run on every complete write | `StateReclamation` → *a cached failure whose test file was deleted stops widening later runs*, *a complete run reclaims the entry and the edge of a deleted test file* |
| 3 | A detached HEAD is read-only for writes, but three paths still *deleted*: structural drift, `--fresh` (`Storage::purge`), and the corrupt-graph discard. The checkout that wiped the baseline could never rebuild it | `Tia::deleteState()` no-ops when detached; `Storage::purge` guarded | `StateReclamation` → the three *a detached HEAD does not purge…* rows |
| 4 | A status int outside 0–8 became `TestStatus::unknown()`, which `ReplayType` folded into `Failure` — a green test went red, exit 1 | `Graph::getResult()` returns `null` for an unknown status (re-run, don't replay); `shouldRerunStatus()` treats unknown as re-run | `HostileState` → *a cached status this build cannot interpret is re-run, not replayed* |
| 5 | Notice/deprecation/warning (3/4/6) decode fine but `ReplayType` had no case, so they also folded into `Failure` | Explicit `Pass` cases — those statuses only reach replay when the configured `failOn*` / `displayDetailsOn*` policies say they do not matter | `HostileState` → *a cached status with no replay of its own does not fail the run* |
| 6 | `Graph::decode()` took `baselines`, `edges` and `files` verbatim; one malformed entry raised a `TypeError` inside a test | `decodeBaselines()` / `decodeResults()` / `decodeEdges()` / `decodeFiles()` validate every field. Numeric-looking keys are cast, not filtered — a branch named `12345` decodes as an `int` | `HostileState` → *a graph whose shape is wrong everywhere is repaired rather than trusted* |
| 7 | Baseline keys grew forever — one full copy of the suite per branch ever created | `ChangedFiles::branchNames()` (local + remote refs) + `Graph::pruneMissingBranches()`, on complete writes only, and only when the fallback branch is visible in the refs | `BranchShapes` → *deleting many branches reclaims every one of their baselines* and the four rows around it |
| 8 | A test that triggered a deprecation was recorded as `status=0`, because PHPUnit emits `Passed` for it and TIA had no issue subscribers. A fresh `--fail-on-deprecation` run exited 1; the replayed one exited 0 | Six subscribers (`Notice`/`PhpNotice`/`Deprecation`/`PhpDeprecation`/`Warning`/`PhpWarning`) feed `ResultCollector`; most-important-status-wins; a plain `Passed` does not downgrade a triggered issue; `@`-suppressed issues are ignored | `IssueStatuses` → *a triggered issue is recorded as itself, not as a pass*, *a cached deprecation still fails the run that asked to fail on one* |
| 9 | A replay wrote back the status it *looked* like from outside, so a cached deprecation replaying as a pass was persisted as `0` — defect 8's fix eroded after one run | `Tia::replayedAsRecorded()` writes back the cached status and message for replayed tests, in the sequential path and in the worker flush | `IssueStatuses` → *replaying a cached issue does not downgrade it to a pass* |
| 10 | A run torn down mid-file (an `exit()` inside a test) still flushed what it had, and the parent read that as licence to prune the siblings it never reached. Sequential and parallel disagreed | `ResultCollector::hasUnfinishedTest()` demotes such a run to results-only, in `terminate()` (the shutdown path) and in `addOutput()` | `StateReclamation` → *a run torn down mid-file does not prune the tests it never reached* |

**One existing assertion was changed.** `DefaultBranchWriteTier > filtered mode falls back to a full
replay when a cached failure cannot be located` used `tests/Unit/DeletedTest.php` — a path that
resolves but does not exist, which is precisely the shape behind defect 2. It now points at
`/build/agent/…`, so it still pins the widening safety net for the case where widening can help. If
you disagree with that reading, that is a finding, not a licence to change it back quietly.

### 1.3 Attacked in phase five and found solid — do not spend time here again

- **Parity.** Sequential vs `--processes=1/2/8` across replay-with-edit, `--filtered` with a cached
  failure, a first run on a feature branch, `--bail`, `--stop-on-failure`, `--compact`: identical
  tally *and* identical graph delta every time.
- **Hostile state dir.** Empty / truncated / not-JSON / JSON scalar / JSON list / `null` / `{}` / NUL
  bytes; `graph.json` as a directory; a read-only state dir; dangling and negative edge ids; a result
  `file` pointing outside the project; `schema: 2`. All degrade to "run the tests", exit 0.
- **Status int mapping.** `ResultCollector` (`asInt()`), `Graph::getResult()` and `TestStatus::from()`
  agree exactly on 0–8. No off-by-one.
- **`FileState::write`** is tmp + rename, so concurrent runs cannot tear a file. Racing runs are
  last-writer-wins **by design** — treat as a decision, not a bug, unless you can show data loss
  worse than that.
- **Branch names.** Slashes, dots, unicode, digits-only, 180 characters, case-only differences,
  remote-only branches, worktree branches: all keyed and reclaimed correctly.
- **`sha`/`tree` layering.** No shape found that under-reports changed files. The fallback `tree` only
  drops a file whose current content hashes identically to what the fallback ran with, which is sound.

### 1.4 Known and deliberately unfixed

| Item | Why it is still open |
|---|---|
| Reclamation is skipped when a complete run executes **zero** tests | Stale edges linger until a run that executes at least one test. Writing the graph from a run that produced nothing costs more than it buys. Revisit if you find a real project stuck there. |
| SIGINT never reaches the suite (`PcovRestarter` re-exec) | Real, but signal handling outside the TIA read/write path. Needs its own pass. |
| Orphaned state dirs when the origin URL changes | `Storage::projectKey()` keys on the origin identity so clones share a graph. Changing the remote silently moves TIA to a fresh dir and nothing reclaims the old one. Unbounded growth in `~/.pest/tia`. |
| `environmental` fingerprint holds only `php_minor` | Nuno declined. The `clearResults()` drift path is therefore latent — it becomes live the moment that bucket grows. **Do not re-flag the `PHP_MAJOR_VERSION` line itself.** |
| PLAN.md §3 (raw coverage flags leave filtered mode on) | **No longer reproduces** — `coverageReportActive()` consults `COVERAGE_REPORT_FLAGS` now. Struck. |

---

## Part 2 — The invariants to attack

Same list as phase five; 1, 2, 4, 5, 7 and 8 are now well covered, so weight your effort toward 3 and
6 and toward the *recording* half of the system, which nothing below the plugin has ever probed.

1. **Parity.** `pest <args>` and `pest --parallel --processes=N <args>` must leave the same graph and
   reach the same tally. Hard rule. `Project::SEQUENTIAL_AND_PARALLEL` encodes it; use it on every new
   write-path row.
2. **The tiers hold.** COMPLETE / RESULTS-ONLY / HARD-SUPPRESSED, exactly one each, no leaking.
3. **Replay is faithful.** ← *weak spot.* A replayed test reports the same status, message, time and
   assertion count as the recorded run. Phase five fixed statuses; **edges** are unproven.
4. **Reads never write.**
5. **A branch never corrupts another branch's baseline.**
6. **Nothing is unbounded.** ← *weak spot.* Baseline keys are reclaimed now; `files`, `edges`, worker
   partials, coverage caches and orphaned state dirs are not.
7. **A hostile state dir cannot break a run.**
8. **Git shapes are all handled.**

---

## Part 3 — Leads to reproduce first (Pass A)

Ranked by expected value. Everything here is **unexplored**, not merely unfixed — phase five never
opened these files.

### M1 — the recording path with a real coverage driver · **highest value**

Everything phase five did was driver-independent by design (seed a graph, never record one). Nothing
verified that a *recorded* graph is correct. This is the biggest blind spot in the audit.

- Do the recorded edges match what the test actually touched? Record with pcov, then hand-check
  `edges` against the source files each fixture test uses.
- `PLAN.md` §4: **`pest --tia --coverage` narrows edges** — `Feature/ExampleTest` recorded with 2
  files instead of 16, dropping self-edges. Same observable shape as the parallel bug G12 that phase
  four closed, likely a different mechanism (the piggyback collector is scoped by `phpunit.xml
  <include>`, the pcov-restarted recorder is not). **Confirm whether these are one fix or two.**
- `Recorder::activateLinkTracking()` (piggyback) vs `activate()` (pcov restart) must produce the same
  edge set for the same suite. Compare them directly.
- `keepExisting: $this->piggybackCoverage` in `replaceEdges()` — what happens to a test whose edges
  genuinely shrank while piggybacking?

Rows for this **must** be `->skipOnPhpVersionsWithoutCoverage()`-style guarded, or seeded, or CI goes
red on `php84`. That constraint is why phase five skipped it; solve it deliberately rather than by
accident. Adding a coverage-driver guard helper to the fixture is a legitimate deliverable.

### M2 — `BaselineSync` (621 lines, never opened)

The remote-baseline fetch is the only path where a graph arrives from **another machine**, which is
exactly where the hostile-state work matters most and where none of it has been exercised.

- A fetched baseline whose `fingerprint` matches but whose `files`/`edges` describe a different tree.
- A fetched baseline recorded on a branch this checkout does not have.
- `fetchIfAvailable()` under a broken network, a 404, a truncated download, a non-gzip body.
- `KEY_FETCH_COOLDOWN` — does it bound retries, and does a corrupt cooldown file break a run?
- Interaction with defect 7's branch GC: a fetched baseline carries branch keys this clone has never
  heard of. **They will be pruned on the next complete write.** Is that right, or must fetched keys be
  exempt? This is a real question, answer it.

### M3 — selection paths nobody has probed

`Graph::affected()` is ~600 lines and phase five only exercised the plain PHP-edge path.

- **Migrations** → `TableExtractor` → `testTables` intersection. What happens with an unparseable
  migration, a migration that drops a table, a squashed schema dump?
- **Blade** — `bladeAncestorsFor()` walks `@include`/`@extends`/`<x-*>` transitively. Cycles?
  Depth? A component referenced only dynamically?
- **Inertia** — `componentForInertiaPage()`, `jsFileToComponents`, `JsModuleGraph::buildStrict()`.
  What if `vite` is missing, or the resolver returns garbage?
- **`usesSiblingHeuristicForUnknownPhp()`** — a hard-coded list of Laravel directories. A changed file
  in `app/Providers/` widens to every test whose deps share that directory. Measure how much that
  over-selects on a real tree.
- **Arch tests** — `testSourceDeclaresArchGroup()` greps the source with three regexes. False
  positives (the string `arch(` in a comment) select the file on *every* PHP source change.

### M4 — git shapes phase five left alone

- A repo with **no commits yet** (`currentSha()` returns null / git fails).
- **Submodules** — a changed file inside one; `git status --porcelain` reports the submodule path.
- A repo whose root is **above** the pest project — `TiaRequiresRepositoryRoot` panics deliberately;
  confirm it still panics and writes nothing.
- **Shallow / single-branch CI checkouts.** Defect 7's guard (`fallbackBranch` must be visible in the
  refs) was reasoned about, not measured. Build one and check nothing is over-pruned.
- A branch **behind** the recorded sha, so `merge-base --is-ancestor` fails and the graph is declared
  unreachable. Does it recover, or thrash?

### M5 — a real concurrent race

Phase five established `FileState::write` is atomic and called it last-writer-wins by design. Nobody
launched two runs. Launch them: a watcher plus a manual run, two `--tia` processes on one project,
`--parallel` where the parent writes while a straggler worker flushes. Look for **loss worse than
last-writer-wins** — a partially-merged baseline, a pruned entry from a run that never saw the file,
worker partials from run A consumed by run B (`KEY_WORKER_*` are not namespaced per run).

That last one is the sharpest: `purgeWorkerPartials()` deletes *all* partials by prefix, so two
concurrent parallel runs will eat each other's. Probe it.

### M6 — the unbounded remainder

Defect 7 reclaimed baseline keys. These still grow:

- `files` and `edges` — a deleted *source* file's entry is never removed (`pruneMissingTests()` only
  covers test files). Every rename leaves an orphan id forever.
- Orphaned state dirs under `~/.pest/tia` (see 1.4).
- `KEY_COVERAGE_CACHE` / `KEY_COVERAGE_MARKER` — who deletes them, and when?
- Worker partials when a worker dies before `terminate()`.

Measure the growth on a realistic tree before proposing anything. Then **ask** — a cap or a GC is a
behaviour choice.

---

## Part 4 — The harness

### 4.1 The scenario suite

`tests/Features/Tia/*` scaffold a throwaway git project into a temp dir, run a **real `pest`
subprocess** against it, and diff the graph it wrote. Everything lives in `tests/Fixtures/Tia/`:

| Class | What it gives you |
|---|---|
| `Project` | `make(branch, overlay:)`, `withoutGit()`, `seed(branch, sentinel:, failing:)`, `seedFor($root, …)`, `pest(...$args)`, `pestWithEnvironment($dir, $env, ...$args)`, `pestIn($dir, ...)`, `write($rel, $contents)`, `path($rel)`, `graph()`, `branchKeys()`, `graphDir()`, `graphExists()`, `snapshot()`, `delta()`, `mutateGraph(fn)`, `addBaseline($branch)`, `worktree($branch)`, `destroy()`, `destroyAll()`, `SEQUENTIAL_AND_PARALLEL` |
| `GitRepo` (`$project->git()`) | `switchTo($b, new:)`, `rename($from, $to)`, `detach()`, `commit($msg)`, `config($k, $v)`, `addOrigin()`, `removeOrigin()`, `setOriginHead($b)`, `unsetOriginHead()`, `worktree()`, `sha()`, `branchNames()`, `run([...])` for anything else |
| `GraphDelta` (`$project->delta()`) | `writtenCount()`, `added()`, `removed()`, `branchKeys()`, `baselineUntouched($b)`, `shaMoved()`, `treeMoved()`, `edgesMoved()`, `filesMoved()`, `fingerprintMoved()`, `structureMoved()`, `isResultsOnly()`, `isHardSuppressed()`, `summary()` |
| `PestResult` (returned by `pest()`) | `replayed()`, `uncached()`, `affected()`, `tally()`, `output`, `exitCode`, `describe()` |

The fixture app is 3 test files / **6 tests** (`Project::TOTAL_TESTS`); `Project::EDGES` and
`Project::TESTS` describe the graph `seed()` writes. `Project::testId($file, $description)` builds a
result key. Overlays in `tests/Fixtures/Tia/overlays/<name>/` supply a different `tests/Pest.php` —
that is how you configure `pest()->tia()->…` for a scenario.

**If the fixture cannot express your case, extend the fixture.** A new `GitRepo` verb, a new overlay,
or a coverage-driver guard is a legitimate part of the deliverable. Do not water down a scenario to
fit the current helpers. Phase five added `GitRepo::commit()` usage, numeric-key handling in
`Project::branchKeys()` and `GraphDelta`, and used `$project->write()` to author test files inline —
follow that pattern.

### 4.2 The sentinel discriminator — read before writing any assertion

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

One addition from phase five: a complete run on a **non-default** branch also sets
`baselines[<branch>].complete = true`. It is deliberately *not* set on the default branch, so a clean
green run there stays byte-identical.

### 4.3 Running them

They are in the `integration` group (`tests/Pest.php`), so `composer test:unit` skips them. **A
directory argument finds nothing** — pass files, space-separated, as separate argv entries:

```bash
PAO_DISABLE=1 php84 bin/pest tests/Features/Tia/BranchShapes.php tests/Features/Tia/CompleteRunWriteTier.php \
  tests/Features/Tia/DefaultBranchReplay.php tests/Features/Tia/DefaultBranchResolution.php \
  tests/Features/Tia/DefaultBranchWriteTier.php tests/Features/Tia/FilteredMode.php \
  tests/Features/Tia/HostileState.php tests/Features/Tia/IssueStatuses.php \
  tests/Features/Tia/PartialRunWriteTier.php tests/Features/Tia/StateReclamation.php

PAO_DISABLE=1 php   bin/pest <same list>
```

**Both interpreters must be green.** `php84` is 8.4.x with **no pcov** — that is what CI has
(`.github/workflows/tests.yml` sets `coverage: none`). `php` is 8.5.x with pcov — that is the dev
machine. Any assertion that depends on a coverage driver passes locally and fails in CI. Concretely:

- A **cold recording run writes no graph at all** without pcov/xdebug (it prints `Running in TIA mode,
  however TIA is skipped as it needs ext-pcov or Xdebug`). **Seed a graph, never record one**, unless
  the driver *is* the point of the row — see M1, which has to solve this properly.
- A **PHP source file edit** driverless triggers `Detected PHP source changes but no coverage driver
  is available` → full suite, `affected=0`. Edit *test* files, not `app/` files.
- The `terminate()` path differs by driver: with pcov the plugin reaches the complete write through
  the shutdown handler, without it the run exits earlier. Defect 10 only reproduced on `php`. **Run
  every probe on both before believing it.**

`PAO_DISABLE=1` is mandatory on every pest invocation and every probe: `laravel/pao` emits JSON under
agents and corrupts the captured output.

**Baseline: 161 scenario rows green on both interpreters**, at `b49ba062`. Per file:

| File | Rows |
|---|---|
| `StateReclamation.php` | 37 |
| `HostileState.php` | 25 |
| `CompleteRunWriteTier.php` | 17 |
| `DefaultBranchResolution.php` | 14 |
| `BranchShapes.php` | 14 |
| `DefaultBranchReplay.php` | 13 |
| `IssueStatuses.php` | 13 |
| `PartialRunWriteTier.php` | 12 |
| `DefaultBranchWriteTier.php` | 10 |
| `FilteredMode.php` | 6 |

Plus 80 unit/arch rows (`tests/Unit/Plugins/Tia/*`, `tests/Arch.php`). If those numbers do not
reproduce, stop and say so.

### 4.4 Measure before you assert

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

---

## Part 5 — Ground rules

- **Do not** run `composer test`. It takes minutes and you do not need it.
- **`tests/.snapshots/success.txt` and the tally in `tests/Visual/Parallel.php` are stale right now** —
  phase five added 89 rows and did not regenerate them. **Report that they need regenerating and let
  Nuno run `composer update:snapshots`.** Do not run it yourself.
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
  fix, that is a finding: report the contradiction and ask. Phase five hit this once (see 1.2) and
  rewrote the row to pin the *narrower* contract rather than deleting it — that is the bar.

---

## Part 6 — Reporting

**Between passes**, not just at the end. Per finding:

- **Reproduced (yes / no / hypothesis only)**, with the exact command and the measured
  `tally` + `delta()->summary()` on **both** interpreters.
- **Severity**, and say why in one line. The scale that matters here: *a test wrongly replayed as
  passing* (worst) > *cache silently useless, full suite forever* > *graph grows / stale data* >
  *cosmetic*.
- **Fixed / deferred / needs-a-decision**, and the row that pins it.
- For anything needing a decision: **one yes/no question**, no essay.

Close with:

1. The count of `tests/Features/Tia/*` green on **both** `php84` (no pcov) and `php` (pcov), against
   the 161 baseline.
2. Every row you added, and what invariant from Part 2 it defends.
3. Which findings are still open, as yes/no questions.
4. That the snapshots need regenerating — **do not regenerate them.**
5. **What you looked at and found solid.** A list of attacks that did not break anything is a real
   result: it tells the next phase where not to spend its time.

---

## Part 7 — Open questions carried into this phase

Answer these before or during Pass C; they change what the fixes should be.

1. A fetched remote baseline carries branch keys this clone has never heard of, and the branch GC will
   prune them on the next complete write. **Should fetched keys be exempt?**
2. Two concurrent parallel runs share the `worker-edges-*` / `worker-results-*` prefixes and
   `purgeWorkerPartials()` deletes by prefix. **Should partials be namespaced per run, or is "do not
   run two TIA suites at once" the contract?**
3. `files` and `edges` never lose a deleted *source* file. **Cap, GC, or accept?**
4. Orphaned state dirs accumulate under `~/.pest/tia` whenever a project's origin URL changes.
   **Reclaim them, or accept?**
