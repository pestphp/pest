# TIA write-tier conformance — plan

Baseline sweep: **147 cases, 144 PASS / 2 FAIL / 1 SKIP** against `dev-fix/tia-filtered` (`db70017`)
on `playground/laravel` with pcov enabled.

This file records (1) what is left to fix, and (2) the full case matrix with the target
outcome for every row, so the sweep can be re-run as a conformance check after the fixes land.

---

## Part 1 — What's left

### 1. `--parallel` silently under-records `edges` (G12, I5) — **high**

Any `--tia --parallel` complete run writes `files=4` where sequential writes `files=25`. Only
`app/**` and blade views survive; every `config/`, `routes/`, `bootstrap/` edge and every test
self-edge is lost. A `routes/web.php` edit that sequential TIA catches, parallel TIA reports as
`No affected tests found`.

Root cause — `src/Restarters/PcovRestarter.php:33`:

```php
if (! Tia::isEnabledForRun($arguments)) {
    return;
}
```

`Tia::isEnabledForRun()` (`src/Plugins/Tia.php:275-288`) returns true only for `--tia` in argv or
the `PEST_TIA` env flag. A paratest worker's argv carries neither, so workers never re-exec with
`-d pcov.directory=<projectRoot>` and record under pcov's empty default scope.

Proven both directions:
- `PEST_PCOV_RESTARTER_RESTARTED=1 pest --tia` (sequential, restart suppressed) reproduces
  `files=4` with edge sets identical to the parallel graph.
- `PEST_TIA=1 pest --parallel` restores the full `files=25` graph. **Working workaround today.**

Coverage config is irrelevant: widening `phpunit.xml` `<include>` to `app|config|routes|bootstrap|tests`
still yields `files=4`.

Sticky, not just per-run: `pest --tia --filtered --parallel` re-records only the affected test
through a worker and strips that entry's edges from an otherwise-healthy sequential graph, so one
parallel run degrades a good graph incrementally.

**Fix direction:** propagate the TIA-enabled decision into workers (e.g. set `PEST_TIA` in the
worker env, or have the parent stamp a recording global the restarter also consults) so
`PcovRestarter` restarts them. Then assert `edges` equivalence between sequential and parallel.

### 2. Deleted test file with a cached failure wedges `--filtered` forever (I9) — **high**

Expected a WARN and a full-suite fallback. Actual: `--filtered` selects the phantom file, runs
nothing, exits **0 green**, on every subsequent invocation.

Root cause — `src/Plugins/Tia/Graph.php:1523-1525`:

```php
if ($real === false) {
    $real = $path;
}
```

`relative()` falls back to the raw path when `realpath()` fails, so a deleted-but-in-project test
file is never "unlocated", `Graph::hasUnlocatedTestsToRerun()` never fires, and the WARN branch at
`src/Plugins/Tia.php:982-986` is unreachable for the deletion case. `pruneMissingTests()` only runs
under `--fresh`, so nothing clears it.

**Fix direction:** distinguish "path could not be resolved" from "path resolved outside the root" in
`relative()`, or have `hasUnlocatedTestsToRerun()` stat the file directly.

### 3. `--coverage-text` does not disable filtered mode (I8) — **low**

`coverageReportActive()` (`src/Plugins/Tia.php:1705-1710`) reads only Pest's own `--coverage`, so
raw PHPUnit coverage flags (`--coverage-text`, `--coverage-html`, `--coverage-clover`, …) leave
filtered mode on. `Tia::COVERAGE_FLAGS` at `:113-115` already enumerates them — that list is not
consulted here.

### 4. `--coverage` narrows edges like parallel does — **medium, needs triage**

`pest --tia --coverage` prints `fresh graph (recording coverage baseline)` even when a graph
exists, and writes `Feature/ExampleTest` with 2 files instead of 16, dropping self-edges. Same
observable shape as G12, likely a different mechanism (piggyback collector scoped by
`phpunit.xml <include>` rather than the pcov-restarted recorder). Theme: **edges silently narrow to
`app/**` whenever recording does not go through the pcov-restarted TIA recorder.** Confirm whether
these are one fix or two.

### 5. Smaller items

| Item | Where | Note |
|---|---|---|
| Warning/deprecation recorded as `status=0` | write path | Codes `6` and `4` appear unreachable; risky/skipped/incomplete record faithfully |
| Complete non-TIA runs write edge-less results | `src/Plugins/Tia.php:1645`, `:638` | Guard is `! $complete && ! knowsTest()`; complete bypasses it and `markKnownTestFiles` stays false. Inert — replay guards on the same predicate at `:360` — but inflates `n` |
| SIGINT never reaches the suite | `PcovRestarter` re-exec | Parent ignores it; the child holds PHPUnit's handler. CI `timeout`/Ctrl-C will not stop a run |
| Structural drift never announced sequentially | `src/Plugins/Tia.php:1178-1181` | `enterRecordMode()` prints a bare `Running in TIA mode.`; `renderFreshGraph()` (`fresh graph (composer.lock changed)`) only runs under `--parallel` or coverage piggyback |
| Replay clobbers cached `time` | write path | `0.058` → `0.001`; timing data degrades toward zero across runs |
| `pest --parallel` without `--tia` writes nothing | G3, G6 | Sequential `pest --filter=…` does record. Parallel CI contributes nothing to the cached-failure replay path |
| `--tia --parallel --filter`/`--shard` record nothing | G2, G8 | More conservative than the results-only contract requires |
| `--min=50` without `--coverage` is a silent no-op | — | — |
| `pest --repeat=2` is not a Pest option | J11 | Case unrunnable as written; drop it or add the option |

### 6. Test-harness gaps to close before re-running

- The playground has no `UsesClass`, `->note()`, `->flaky()`, `->issue()`, `->pr()`, `->ticket()` or
  `->assignee()` annotations, so **C19, C23–C28, C37–C39 matched zero tests** and their
  graph-invariant assertions proved nothing. Add annotated fixtures to make those rows load-bearing.
- Use **sentinel patching** (rewrite every cached entry to `time=9.999 assertions=42`, then see which
  entries get overwritten) as the discriminator. It is the only way to tell "wrote identical values"
  from "wrote nothing". A canary test absent from `edges` is unreliable under `--filter` because it
  never matches the filter and so never runs.
- `--tia` on a clean green tree can never cache a failure (unchanged tests replay rather than
  execute); seeding one requires `--fresh` or an env-driven flaky fixture.
- Comment-only edits to a test file are **not** changes (AST-level hashing). Use semantic edits.

---

## Part 2 — Full case matrix (target outcomes)

Tiers: **COMPLETE** may change everything · **RESULTS-ONLY** (RO) may change only
`baselines[<branch>].results` for tests that ran, and must never remove an entry, add a result for a
test file absent from `edges`, or alter `sha`/`tree`/`edges`/`files`/`fingerprint` ·
**HARD-SUPPRESSED** may change nothing.

Status column: `PASS` = conforming today · `FIX` = must pass once the item above lands ·
`SKIP` = case not runnable as written.

### A — Setup & sanity

| # | Case | Target outcome | Status |
|---|---|---|---|
| A1 | `composer show pestphp/pest` | version `dev-fix/tia-filtered` | PASS |
| A2 | `pest --baseline` | prints an existing dir; exit 0 | PASS |
| A3 | delete graph, `pest --tia` | graph.json created; one `edges` key per test file; one result per test | PASS |
| A4 | `git status` after reset ritual | clean | PASS |
| A5 | `extension_loaded("pcov")` | `true` | PASS |
| A6 | delete graph, plain `pest` | no graph created | PASS |
| A7 | delete graph, `pest --filter=adds` | no graph created | PASS |
| A8 | two consecutive `pest --tia` | second replays everything; `sha` unchanged | PASS |

### B — COMPLETE runs still write

| # | Case | Target outcome | Status |
|---|---|---|---|
| B1 | `pest --tia` (clean) | replays; graph written; `sha` = `git rev-parse HEAD` | PASS |
| B2 | `pest --no-tia` | results refreshed; prune applied; `sha`/`tree`/`edges` unchanged | PASS |
| B3 | `pest` (plain) | same as B2 | PASS |
| B4 | `pest --tia --fresh` | graph purged and rebuilt; `edges` rebuilt; `files` repopulated | PASS |
| B5 | `pest --bail`, all green | COMPLETE — graph written, prune applied | PASS |
| B6 | edit `Calculator.php`, `pest --tia` | `CalculatorTest` re-runs, others replay; `tree` updated | PASS |
| B7 | delete `trio three`, `pest --tia` | its result pruned; `trio one`/`two` remain | PASS |
| B8 | delete `GreeterTest.php`, `pest --tia` | result and edges survive (missing-file prune is `--fresh`-only) | PASS |
| B9 | B8 then `pest --tia --fresh` | entry gone; `files` drops `Greeter.php` too | PASS |
| B10 | `pest --tia` twice | `time` differs; statuses stable | PASS |
| B11 | add a test file, `pest --tia` | new `edges` key + new result appear | PASS |
| B12 | `pest --tia --coverage` | completes; graph written | PASS |

### C — Selection narrowing → RESULTS-ONLY

All rows: RO invariants hold. "Notice" = `TIA does not apply to partial runs — running the selected tests directly.`

| # | Case | Target outcome | Status |
|---|---|---|---|
| C1 | `pest --filter="adds numbers"` | RO; no notice | PASS |
| C2 | `pest --tia --filter="adds numbers"` | RO; notice | PASS |
| C3 | `pest --filter="trio one"` | RO; `trio two`/`three` byte-identical | PASS |
| C4 | `pest --filter="trio"` | RO; all three update; nothing else does | PASS |
| C5 | `pest --exclude-filter="Feature"` | RO; no notice; no prune | PASS |
| C6 | `pest --tia --exclude-filter="Feature"` | RO; notice | PASS |
| C7 | `pest --group=smoke` | RO; no notice | PASS |
| C8 | `pest --tia --group=smoke` | RO; notice | PASS |
| C9 | `pest --exclude-group=smoke` | RO; no notice; no prune | PASS |
| C10 | `pest --tia --exclude-group=smoke` | RO; notice | PASS |
| C11 | `pest tests/Unit` | RO; no notice; Feature entry not pruned | PASS |
| C12 | `pest --tia tests/Unit` | RO; notice | PASS |
| C13 | `pest tests/Unit/TrioTest.php` | RO; no notice | PASS |
| C14 | `pest --tia tests/Unit/TrioTest.php` | RO; notice | PASS |
| C15 | `pest --testsuite=Unit` | RO; no notice | PASS |
| C16 | `pest --tia --testsuite=Unit` | RO; notice | PASS |
| C17 | `pest --tia --exclude-testsuite=Feature` | RO; notice | PASS |
| C18 | `pest --tia --covers='App\Services\Calculator'` | RO; notice | PASS |
| C19 | `pest --tia --uses='App\Services\Calculator'` | RO; notice | PASS (vacuous — needs fixture) |
| C20 | `pest --tia --test-suffix=Test.php` | RO even though all tests run; `edges`/`files`/`n` unchanged | PASS |
| C21 | `pest --dirty` with an uncommitted test edit | RO; no notice | PASS |
| C22 | `pest --tia --dirty` | RO; notice | PASS |
| C23 | `pest --tia --todos` | RO; notice | PASS (vacuous) |
| C24 | `pest --tia --notes` | RO; notice | PASS (vacuous) |
| C25 | `pest --tia --flaky` | RO; notice | PASS (vacuous) |
| C26 | `pest --tia --issue=123` | RO; notice | PASS (vacuous) |
| C27 | `pest --tia --pr=1` | RO; notice | PASS (vacuous) |
| C28 | `pest --tia --pull-request=1` | RO; notice | PASS (vacuous) |
| C29 | `pest --tia --shard=1/2` | RO; notice; no prune | PASS |
| C30 | `pest --tia --shard=2/2` | RO; notice; C29+C30 covers the suite; neither prunes | PASS |
| C31 | `->only()` on `trio one`, `pest --tia` | RO; no notice; siblings untouched | PASS |
| C32 | `->only()` on `trio one`, plain `pest` | RO; no notice | PASS |
| C33 | `pest --tia --filtered --filter=adds` | RO; notice; filtered yields to narrowing | PASS |
| C34 | `PEST_TIA=1 pest --filter=adds` | RO; notice | PASS |
| C35 | `PEST_TIA_FILTERED=1 pest --filter=adds` | RO; notice (regression guard) | PASS |
| C36 | `pest --filtered --filter=adds` | RO; notice | PASS |
| C37 | `pest --tia --ticket=X` | RO; notice. `--ticket` **is** a real option (`src/Plugins/Snapshot.php:83`) — it narrows legitimately | PASS (vacuous) |
| C38 | `pest --tia --assignee=X` | RO; notice (`src/Plugins/Snapshot.php:81`) | PASS (vacuous) |
| C39 | `pest --tia --todo` | RO; notice; matches nothing | PASS (vacuous) |

### D — Truncation → RESULTS-ONLY

D1–D5, D11–D13, D16 precondition: `trio one` broken. D6–D10 run against a green suite so the `--stop-on-*` trigger is the only narrowing.

| # | Case | Target outcome | Status |
|---|---|---|---|
| D1 | `pest --bail` | RO; `trio one` = `status=7`; siblings survive unchanged | PASS |
| D2 | `pest --retry` | RO; siblings survive | PASS |
| D3 | `pest --stop-on-failure` | RO | PASS |
| D4 | `pest --stop-on-defect` | RO | PASS |
| D5 | `pest --stop-on-error` (no error occurs) | COMPLETE — nothing stopped it | PASS |
| D6 | `pest --stop-on-warning` | RO when it fires. Warning stored as `status=0`, not `6` | PASS |
| D7 | `pest --stop-on-risky --disallow-test-output` | RO; `status=5` | PASS |
| D8 | `pest --stop-on-skipped` | RO; `status=1` | PASS |
| D9 | `pest --stop-on-incomplete` | RO; `status=2` | PASS |
| D10 | `pest --stop-on-deprecation` | RO. Deprecation stored as `status=0`, not `4` | PASS |
| D11 | `pest --tia --bail` | RO | PASS |
| D12 | `pest --bail --filter=trio` | RO (narrowed *and* truncated) | PASS |
| D13 | `stopOnFailure="true"` in `phpunit.xml`, plain `pest` | RO — caught via `stoppedEarly()`, not flag matching | PASS |
| D14 | D13 config, green suite | COMPLETE | PASS |
| D15 | `pest --bail`, last test in run order fails | RO — over-conservative but safe; nothing was skipped | PASS |
| D16 | `pest --tia --filtered --bail`, broken affected test | RO | PASS |

### E — Result merge semantics

| # | Case | Target outcome | Status |
|---|---|---|---|
| E1 | cached `flaky`=7, `FLAKY_OK=1 pest --filter=flaky` | flips to `status=0`; all other results byte-identical | PASS |
| E2 | cached `flaky`=0, `pest --filter=flaky` (env unset) | flips to `status=7` | PASS |
| E3 | after E1, `pest --tia --filtered` clean | `No affected tests found`; zero graph delta | PASS |
| E4 | after E2, `pest --tia --filtered` | re-runs, `from 1 previously unsuccessful test` | PASS |
| E5 | any partial run | `assertions` and `time` update for the test that ran | PASS |
| E6 | any partial run | `message` of untouched tests unchanged | PASS |
| E7 | two sequential partial runs on different tests | each updates only its own entry; both persist | PASS |
| E8 | `pest --filter="trio one"` | siblings keep exact `status`/`time`/`assertions`/`message` | PASS |
| E9 | partial run of a `->skip()`ed test | `status=1` | PASS |
| E10 | partial run of a `->todo()` test | `status=1`, `assertions=0`, `message="__TODO__"` | PASS |
| E11 | partial run of a risky test | `status=5` | PASS |
| E12 | any partial run | `fingerprint` byte-identical | PASS |
| E13 | dataset test, `--filter` matching one row | that row updates; other rows survive (the old prune bug) | PASS |
| E14 | dataset test, full `--tia` after deleting a row | the deleted row is pruned | PASS |

### F — Guard rails

| # | Case | Target outcome | Status |
|---|---|---|---|
| F1 | test absent from `edges`, `pest --filter="brand new"` | no result recorded | PASS |
| F2 | complete `pest --tia` first, then the same filter | result is recorded | PASS |
| F3 | `pest --mutate --path=app/Services --covered-only` | statuses/`edges`/`sha`/`tree`/`files` identical; only `time` may change | PASS |
| F4 | `pest --mutate --parallel --path=… --covered-only` | same (observed: zero delta) | PASS |
| F5 | weakened test so a mutant survives, `pest --mutate` | no status change in the baseline | PASS |
| F6 | `pest --mutate` with the graph deleted | no graph created | PASS |
| F7 | mutation subprocess env | carries `PEST_MUTATION_TESTING`; `--mutate` stripped from argv | PASS |
| F8 | grep baseline after `--mutate` | no mutation-flavoured messages | PASS |

### G — Parallel

| # | Case | Target outcome | Status |
|---|---|---|---|
| G1 | `pest --tia --parallel` (clean) | COMPLETE — graph written | PASS |
| G2 | `pest --tia --parallel --filter=adds` | RO | PASS |
| G3 | `pest --parallel --bail`, `trio one` broken | RO; siblings survive | PASS |
| G4 | `pest --tia --parallel --bail` | RO | PASS |
| G5 | `pest --tia --filtered --parallel` after a source edit | narrows to affected; writes | PASS (but see G12 — it strips edges) |
| G6 | `pest --tia --parallel` | worker results reach the parent baseline | PASS |
| G7 | `->only()` + `pest --tia --parallel` | RO | PASS |
| G8 | `pest --tia --parallel --shard=1/2` | RO | PASS |
| G9 | broken test in one worker, `pest --parallel --bail` | whole run is RO, not just that worker's slice | PASS |
| G10 | `pest --parallel --retry` | `InvalidOption`; graph untouched | PASS |
| G11 | `pest --tia --parallel --fresh` | graph rebuilt | PASS |
| G12 | `edges` after `pest --tia` vs `pest --tia --parallel --fresh` | **equivalent edge sets** (`files=25` both) | **FIX (item 1)** |

### H — Baseline key / branch resolution

| # | Case | Target outcome | Status |
|---|---|---|---|
| H1 | `pest --tia` on `master` | only a `master` key | PASS |
| H2 | plain `pest` on `master` | writes `master`, not `main` | PASS |
| H3 | `pest --bail` truncated on `master` | no `main` key minted | PASS |
| H4 | `pest --filter=adds` on `master` | no `main` key minted | PASS |
| H5 | `git branch -m main`, full `pest --tia` | single `main` key | PASS |
| H6 | `git checkout -b feature/x`, `pest --tia` | `feature/x` key appears; reads fall back to the existing baseline | PASS |
| H7 | detached HEAD, `pest --tia` | falls back to the `main` key for both reads and writes; no `HEAD` key | PASS |
| H8 | after every C and D case | no baseline key other than the real branch | PASS |
| H9 | non-git dir, `pest --tia` | `MissingDependency` — `The feature "Tia mode" requires "git".` | PASS |
| H10 | non-git dir, plain `pest` | runs normally; no baseline dir created | PASS |

### I — Filtered mode

| # | Case | Target outcome | Status |
|---|---|---|---|
| I1 | clean + green, `pest --tia --filtered` | `No affected tests found`; zero graph delta | PASS |
| I2 | edit `Calculator.php` | only `CalculatorTest` runs | PASS |
| I3 | cached failure, clean tree | re-runs it, `from 1 previously unsuccessful test` | PASS |
| I4 | I2 with sentinels | `tree` updated; exactly one entry rewritten; all others retained | PASS |
| I5 | `pest --tia --filtered --parallel` | same narrowing as I2 **and `edges` unchanged** | **FIX (item 1)** |
| I6 | `PEST_TIA_FILTERED=1 pest --tia` | identical delta to I4 | PASS |
| I7 | `pest --tia --filtered tests/Unit` | explicit path wins; filtered off; RO; notice | PASS |
| I8 | `pest --tia --filtered --coverage-text` | filtered mode disabled by an active coverage report | **FIX (item 3)** |
| I9 | cached failure whose test file was deleted | WARN `could not be located on disk`; falls back to the full suite with replay | **FIX (item 2)** |
| I10 | `pest --tia --filtered` with no baseline yet | records a baseline instead of filtering | PASS |
| I11 | edit a Blade view a Feature test renders | the Feature test is selected | PASS |
| I12 | edit `composer.lock` | fingerprint drift → full rebuild (and the drift reason should be printed — item 5) | PASS |

### J — Interactions & regressions

| # | Case | Target outcome | Status |
|---|---|---|---|
| J1 | `pest --tia --fresh --filter=adds` | graph **not** purged; RO | PASS |
| J2 | `pest --tia --refetch --filter=adds` | refetch not performed; RO | PASS |
| J3 | `pest --no-tia --filter=adds` | RO — narrowing wins over `--no-tia`; no notice | PASS |
| J4 | `pest --tia --no-tia` | TIA disabled; COMPLETE | PASS |
| J5 | `<groups><exclude><group>integration</group></exclude></groups>`, `pest --tia` | COMPLETE, no notice — an always-in-force filter applies to baseline runs too | PASS |
| J6 | `pest --tia --compact` | COMPLETE | PASS |
| J7 | `pest --tia -v` | COMPLETE | PASS |
| J8 | `pest --tia --profile` | COMPLETE | PASS |
| J9 | `pest --tia --order-by=random` | COMPLETE | PASS |
| J10 | `pest --tia --random-order-seed=1234` | COMPLETE | PASS |
| J11 | `pest --tia --repeat=2` | COMPLETE — repetition is not narrowing | **SKIP** — `--repeat` is not a Pest option |
| J12 | `pest --filter=adds` twice | only the executed test's `time` differs; rest byte-identical | PASS |
| J13 | `pest --tia --min=50` | COMPLETE | PASS |
| J14 | SIGINT mid-suite | RO — an interrupted run is truncated | PASS (signal must go to the re-exec'd child — item 5) |
| J15 | delete the graph, `pest --tia --filtered` | records a baseline rather than erroring | PASS |
| J16 | corrupt `graph.json`, `pest --tia` | recovers by rebuilding; no crash | PASS |

---

## Re-run procedure

```bash
cd /Users/nunomaduro/Work/projects/playground/laravel
GRAPH="$(PAO_DISABLE=1 ./vendor/bin/pest --baseline)/graph.json"

# per case
git checkout -q . && git clean -qfd tests app
rm -rf "$(PAO_DISABLE=1 ./vendor/bin/pest --baseline)"
PAO_DISABLE=1 ./vendor/bin/pest --tia >/dev/null 2>&1
# sentinel-patch every result to time=9.999 assertions=42, snapshot, run the case, diff
```

Every pest invocation must be prefixed with `PAO_DISABLE=1` (the app has `laravel/pao`, which emits
JSON when it detects an agent). The shell is zsh — build commands with arrays or `eval`; unquoted
`$args` does not word-split.
