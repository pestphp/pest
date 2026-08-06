# TIA defect sweep — phase four

## Your task

Five defects in TIA's read/write path, found while building the repo's TIA scenario suite. One is
confirmed and load-bearing (**B1**); four need a decision before a fix (**B2**–**B5**).

Work **one bug at a time, in order**, and for each:

1. **Reproduce it as a repo test first.** The reproduction is the deliverable even when the fix is
   deferred — a red test that pins the exact symptom is worth more than a prose report. Do not commit
   a red test to the suite; keep it in a scratch file until the fix lands (see Part 1.4).
2. Confirm the measured numbers in this file still hold. They were taken at commit `4d3d0105` plus
   the two uncommitted changes described in Part 2. If a number has moved, say so and stop.
3. Fix, then re-run **the whole `tests/Features/Tia/*` set on two interpreters** (Part 1.3).
4. **HARD STOP after B1.** Report the diff and wait — B1's fix changes `Graph`'s read semantics for
   every caller, and Nuno wants to see it before B2–B5 pile on top.

Per `CLAUDE.md`: do not run `composer test`, and do not regenerate snapshots unless told. Do not
commit. Do not touch the playground's `vendor/`.

---

## Part 1 — The harness

### 1.1 What exists

`tests/Features/Tia/*` scaffold a throwaway git project into a temp dir, run a **real `pest`
subprocess** against it, and diff the TIA graph it wrote. Everything lives in
`tests/Fixtures/Tia/`:

| Class | What it gives you |
|---|---|
| `Project` | `make(branch, overlay:)`, `withoutGit()`, `seed(branch, sentinel:, failing:)`, `pest(...$args)`, `pestWithEnvironment($dir, $env, ...$args)`, `pestIn($dir, ...)`, `write($rel, $contents)`, `graph()`, `branchKeys()`, `graphDir()`, `graphExists()`, `snapshot()`, `delta()`, `mutateGraph(fn)`, `addBaseline($branch)`, `worktree($branch)`, `destroyAll()` |
| `GitRepo` (`$project->git()`) | `switchTo($b, new:)`, `rename($from, $to)`, `detach()`, `config($k, $v)`, `addOrigin()`, `removeOrigin()`, `setOriginHead($b)`, `unsetOriginHead()`, `worktree()`, `sha()`, `branchNames()` |
| `GraphDelta` (`$project->delta()`) | `writtenCount()`, `added()`, `removed()`, `branchKeys()`, `baselineUntouched($b)`, `shaMoved()`, `treeMoved()`, `edgesMoved()`, `filesMoved()`, `fingerprintMoved()`, `structureMoved()`, `isResultsOnly()`, `isHardSuppressed()`, `summary()` |
| `PestResult` (returned by `pest()`) | `replayed()`, `uncached()`, `affected()`, `tally()`, `output`, `exitCode`, `describe()` |

The fixture app is 3 test files / **6 tests** (`Project::TOTAL_TESTS`), with `Project::EDGES` and
`Project::TESTS` describing the graph `seed()` writes. `Project::testId($file, $description)` builds
a result key.

### 1.2 The sentinel discriminator — read this before writing an assertion

`seed()` writes a graph **and then rewrites every cached result to `time=9.999`,
`assertions=42`** (non-zero assertion counts only — risky/skipped/incomplete statuses are *derived*
from "performed no assertions", so falsifying those would rewrite the status on replay), and takes a
snapshot. Therefore:

- `$delta->writtenCount()` is the only reliable way to tell **"replayed"** from **"executed and wrote
  back the same values"**. `0` means nothing was written.
- `isResultsOnly()` = no structure moved, no `sha`/`tree` movement, nothing added or removed.
- `isHardSuppressed()` = the graph is byte-identical.

Tiers, unchanged since phase two: **COMPLETE** may change everything · **RESULTS-ONLY** may change
only `baselines[<branch>].results` for tests that ran, never removing an entry, never adding a result
for a test file absent from `edges`, never touching `sha`/`tree`/`edges`/`files`/`fingerprint` ·
**HARD-SUPPRESSED** may change nothing.

### 1.3 Running them

They are in the `integration` group (`tests/Pest.php:22`), so `composer test:unit` skips them. A
**directory argument finds nothing** — pass files:

```bash
F="tests/Features/Tia/DefaultBranchReplay.php tests/Features/Tia/DefaultBranchResolution.php \
   tests/Features/Tia/DefaultBranchWriteTier.php tests/Features/Tia/PartialRunWriteTier.php \
   tests/Features/Tia/CompleteRunWriteTier.php tests/Features/Tia/FilteredMode.php"

PAO_DISABLE=1 php84 bin/pest $F     # 8.4.23, NO pcov — this is what CI has
PAO_DISABLE=1 php   bin/pest $F     # 8.5.8, pcov — this is what your machine has
```

**Both must be green.** `.github/workflows/tests.yml` sets `coverage: none`, so any assertion that
depends on a coverage driver fails in CI while passing locally. Two tests were already caught by
this. Concretely: a **cold recording run writes no graph at all** without pcov/xdebug (it prints
`Running in TIA mode, however TIA is skipped as it needs ext-pcov or Xdebug`), and a **PHP source
file edit** triggers `Detected PHP source changes but no coverage driver is available` → full suite,
`affected=0`. Seed a graph instead of recording one, and edit *test* files rather than `app/` files,
unless the point of the row is the driver itself.

### 1.4 Measure before you assert

Do not guess expectations from reading the code — every number in Part 3 came from a scratch probe.
The pattern (put it in your scratchpad, not in the repo):

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

`PAO_DISABLE=1 php probe.php`. One project per scenario; always `destroy()`.

---

## Part 2 — What the code looks like right now

Phase three landed default-branch resolution: `ChangedFiles::defaultBranch()`, the
`pest()->tia()->defaultBranch()` config surface, `Graph::setFallbackBranch()` + `?string
$fallbackBranch = null` on the seven read methods, and `Tia::resolveFallbackBranch()`
(`Tia.php:~1776`) resolving **config → CI env (`CiDefaultBranch`) → git (`origin/HEAD`, then
`init.defaultBranch` if the branch exists) → `soleRecordedBranch()`**, failing loudly when nothing
can name it.

On top of that, **two uncommitted changes** you will see in `git diff`:

1. `TIA_RESULTS_ONLY` global — a *partial* parallel run with an existing graph now purges stale
   worker partials, sets the global, and workers flush their results through the existing
   `flushWorkerReplay()` / `mergeWorkerReplayPartials()` path; the parent writes them with
   `complete: false`. Gated on a graph already existing, so a TIA-less project still creates no
   baseline dir. This gave `--parallel --filter` parity with sequential — **and, per B1, handed it
   the shadowing bug too.**
2. `loadGraph()` emits `WARN The dependency graph could not be read — it will be rebuilt.` once per
   parent process when `graph.json` exists but will not decode. Previously silent.

62 scenario tests cover this and pass on both interpreters.

---

## Part 3 — The defects

### B1 — a thin baseline key permanently shadows the default-branch fallback · **confirmed, priority 1**

**Symptom.** Any *narrowed* run on a new branch (`--filter`, `--group`, a path, `--bail`, `--shard`,
and now `--parallel --filter`) writes a baseline key holding only the tests that ran. From then on
`--tia` on that branch reads that thin key instead of falling back to the default branch, so
everything else is uncached — **one full suite per branch, forever**, which is the exact cost
issue [#1823](https://github.com/pestphp/pest/issues/1823) was about, re-entering through a side door.

**Measured** (fixture: 6 tests, graph seeded on `master`):

```
switch -c feature-x; pest --filter="adds two numbers"
  → keys=[master,feature-x]        (feature-x holds 1 result)
pest --tia
  → 6 passed (6 assertions, 5 uncached, 1 replayed)      ← want: 6 replayed, 0 uncached

same via: pest --parallel --processes=2 --filter="adds two numbers"   → identical
```

**Where.** `src/Plugins/Tia/Graph.php::baselineFor()` (~line 814):

```php
if (isset($this->baselines[$branch]))                                        return $this->baselines[$branch];
if ($branch !== $fallbackBranch && isset($this->baselines[$fallbackBranch])) return $this->baselines[$fallbackBranch];
```

The fallback is all-or-nothing: it fires only when the branch has **no** key at all. The key itself
is minted by `Graph::setResult()` → `ensureBaseline($branch)` (~599 / ~829), reached from
`Tia::snapshotTestResults()` on partial runs.

**Reproduction to add** (`tests/Features/Tia/DefaultBranchReplay.php`):

```php
test('a narrowed run on a new branch does not cost the fallback', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--filter=adds two numbers');

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->skipOnWindows();
```

Add the `--parallel --processes=2 --filter=…` variant as a second row (dataset), since the two write
paths are different code.

**Fix direction.** Make the fallback **per entry** rather than per baseline: in `baselineFor()`,
when the branch has its own baseline *and* a distinct fallback baseline exists, return
`results` = the branch's results **layered over** the fallback's (branch wins per test id), and take
`sha`/`tree` from the fallback when the branch's are `null`/empty. `baselineFor()` is the single
funnel for `recordedAtSha()`, `lastRunTree()`, `getResult()`, `getTime()`, `getAssertions()`,
`testFilesToRerun()` and `hasUnlocatedTestsToRerun()`, so one change covers every reader. An
alternative — never mint a key from a partial run — is smaller but loses the executed result
entirely, which regresses the parity just gained.

**Done when.** Both reproduction rows are green on both interpreters, and none of these move:

- `the branch that ran gets its own key and the default branch keeps its baseline` — writes stay on
  the real branch; the merge must be **read-only** and must not leak into `ensureBaseline()`/`setResult()`.
- `a declared default branch that does not exist degrades to a full run` — with a fallback that names
  nothing, a branch's own thin results must still be all you get.
- `a detached HEAD replays without minting a branch key`, `writes nothing on a second run on the same
  branch`, `filtered mode finds nothing to do…` (both rows) — a merged read must not make a clean
  replay start writing.
- The whole `PartialRunWriteTier.php` / `CompleteRunWriteTier.php` set — tier semantics are unchanged
  by this fix.

---

### B2 — a partial run on detached HEAD writes into the default branch's baseline · **needs a decision**

**Symptom.** With `HEAD` detached, `Tia::resolveBranch()` (`Tia.php:~1756`) sets
`$this->branch = $changedFiles->currentBranch() ?? $this->fallbackBranch` — and that branch is used
for **writes**. A `--tia` run in this state happens to be harmless (a clean replay writes nothing),
but any run that *executes* tests writes their results into the default branch's baseline.

**Measured.**

```
seed on master; git checkout --detach; pest --filter="adds two numbers"
  → keys=[master]   w=1 struct:ok        ← master's baseline rewritten from a detached checkout
seed on master; git checkout --detach; pest --tia
  → keys=[master]   w=0                  ← read-only, as intended
```

**The decision.** `PLAN_PHASE_THREE.md` §2.3 **D3** recommended detached HEAD be *read-only*. If that
still stands, suppress writes when `currentBranch()` is `null` (a dedicated flag — note
`resultsOnlyWrites` is **not** enough, it still writes results). If Nuno prefers the current
behaviour, add a test pinning it and close this out.

**Reproduction** (`tests/Features/Tia/DefaultBranchWriteTier.php`), written for the read-only answer:

```php
test('a detached HEAD does not write into the default branch baseline', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->detach();
    $project->pest('--filter=adds two numbers');

    $delta = $project->delta();

    expect($delta->baselineUntouched('master'))->toBeTrue($delta->summary())
        ->and($project->branchKeys())->toBe(['master']);
})->skipOnWindows();
```

---

### B3 — an unreadable graph is never repaired on a machine with no coverage driver · **needs a decision**

**Symptom.** A corrupt `graph.json` is now *reported* (Part 2, change 2) but only *rebuilt* when a
coverage driver is present, because rebuilding means recording. Driverless, the file stays corrupt
run after run and TIA is silently inert until someone deletes it by hand — while the WARN claims
`it will be rebuilt`.

**Measured** (`php84`, no pcov):

```
overwrite graph.json with '{not json'
run 1: exit=0, 6 passed, file still '{not json'
run 2: exit=0, 6 passed, file still '{not json'
headline: "Running in TIA mode, however TIA is skipped as it needs ext-pcov or Xdebug"
```

**Options.** (a) delete the file when it cannot be decoded, so the next drivered run starts clean and
the state dir does not carry a permanent landmine; (b) keep the file but reword the WARN when no
driver is available. (a) is the honest one and costs one `State::delete()`.

**Reproduction** (`tests/Features/Tia/FilteredMode.php`, extending the existing corrupt-graph row):

```php
expect($result->output)->toContain('The dependency graph could not be read')
    ->and(file_get_contents($project->graphDir().'/graph.json'))->not->toBe('{not json');
```

Must pass on **both** interpreters — that is the whole point of the row.

---

### B4 — a complete `--parallel` run writes nothing and prunes nothing · **needs a decision**

**Symptom.** With a graph present and TIA not flagged, a sequential run refreshes results and applies
the prune; the same run under `--parallel` does neither, because the parent's `ResultCollector` is
empty (results live in the workers) and workers only flush when they were told to record, replay, or
— since Part 2's change — results-only for a *partial* run. So parallel CI contributes nothing to the
cache, and a deleted test's entry survives forever.

**Measured** (graph seeded on `master`, sentinelled):

```
pest                                  → w=6  +0 -0 struct:ok      (sequential baseline)
pest --parallel --processes=2         → w=0  +0 -0 struct:ok      ← writes nothing
delete a test, then:
pest --parallel --processes=2         → w=0  +0 -0 struct:ok      ← and does not prune (sequential gives -1)
pest --tia --parallel --processes=2   → w=0                       ← correct: everything replayed
```

**The decision.** Extending the `TIA_RESULTS_ONLY` mechanism to complete parallel runs is
mechanically easy, but a *complete* run also prunes, and pruning from merged worker partials is the
risky half: a worker that dies, or a shard that never ran, would look like "these tests no longer
exist". If it is done, the prune must key off "every worker reported" and fall back to
results-only when it cannot prove that. `PLAN.md` §5 lists this as a known gap, not a regression.

**Reproduction** (`tests/Features/Tia/CompleteRunWriteTier.php`) — mirror the two sequential rows
that already exist (`a complete run prunes a deleted test`, `--no-tia refreshes results…`) with
`--parallel --processes=2` added, and assert the same deltas.

---

### B5 — G4 ("parallel replay clobbers cached `time`") no longer reproduces · **verify, then correct the record**

**Symptom.** `PLAN_PHASE_THREE.md` §4.6 lists as still-present: *"parallel replay clobbers cached
`time` on all non-executed tests — `mergeWorkerReplayPartials()` takes `$result['time']` verbatim,
never routing through `resultTime()`"*. The repo fixture disagrees: after a parallel replay the
sentinelled `time=9.999` / `assertions=42` survive on every non-executed test.

**Measured.** `a parallel run merges worker results into the parent baseline` edits one test file,
then runs `--tia --parallel --processes=2`: `2 affected, 4 replayed, w=2`. If replayed times were
being clobbered, `w` would be `6`.

**Why the record may be stale.** `flushWorkerReplay()` (`Tia.php:~1286`) already applies
`resultTime()` **worker-side** before writing the partial, so the parent's verbatim read is reading
values that were already corrected.

**What to do.** Either find a shape where it still reproduces (the playground has 25 tests and real
timings; the fixture has 6 and may be too small), or confirm it is fixed and strike it from §4.6.
Add a direct row either way:

```php
test('a parallel replay keeps the recorded time of tests that did not run', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->pest('--tia', '--parallel', '--processes=2');

    $delta = $project->delta();

    expect($delta->writtenCount())->toBe(0, $delta->summary())
        ->and($delta->isResultsOnly())->toBeTrue($delta->summary());
})->skipOnWindows();
```

---

## Part 4 — Reporting

Per bug: **reproduced (yes/no)** with the measured delta, **fixed (yes/no/deferred)**, and the test
that now pins it. Close with:

1. Whether all `tests/Features/Tia/*` are green on **both** `php84` (no pcov) and `php85` (pcov).
2. Which of B2–B5 still need Nuno's decision, phrased as a yes/no question each.
3. Whether `tests/.snapshots/success.txt` and the `tests/Visual/Parallel.php` tally now need
   regenerating (they will, if you added rows) — **do not run `composer update:snapshots` unless
   asked.**
4. Anything you found that is not in this file.

Leave the tree uncommitted and the scratch probes out of the repo.
