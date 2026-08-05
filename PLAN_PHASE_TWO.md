# TIA write-tier conformance — phase two

## Your task

**Re-run all 156 rows of the matrix in Part 2 from scratch, against the playground app** at
`/Users/nunomaduro/Work/projects/playground/laravel`. Every row, including the ones already marked
VERIFIED or PASS — the point of phase two is that no row's status is trusted until it has been
re-measured against the current code. The `Phase 1` column is prior evidence and a hint at what to
watch, never a reason to skip a row.

These are **not** the `pestphp/pest` repo's own tests (`composer test`) — do not run those. Each row is
a `pest` invocation against the playground's suite, followed by a diff of the TIA graph it wrote.

Report, per row: tier respected (yes/no), the graph delta under sentinel patching, and — for any
failure — the pre-fix contrast so a regression is told apart from a pre-existing defect. Do **not**
stop at reading this file or summarising it; the deliverable is executed results.

Work in this order: **Part 1 (environment traps) → Part 1b (build the fixtures — the playground has
none of them) → Part 2 (the matrix) → Part 3 (priorities)**. The matrix is too large for one context;
take it one lettered section at a time and report as you finish each. Sections A, B, G, I and K are
the load-bearing ones — do those first if you run short.

Phase one implemented `PLAN.md` Part 1 items 1–4 plus three §5 items. This file turns the matrix into
a conformance check rather than a bug list.

Code under test: `pestphp/pest` at `/Users/nunomaduro/Work/projects/pestphp/pest`, branch
`fix/tia-filtered`, commit **`bfd5b756`** or later. Verify with
`grep -c 'recordsEdgesInWorkers\|recordsEdges' src/Plugins/Tia.php` → at least 3 hits. Pre-fix
baseline for every contrast is **`db70017c`**.

---

## Part 0 — What phase one changed

| # | Change | Files |
|---|---|---|
| 1 | `hasUnlocatedTestsToRerun()` stats the file, so a deleted test file is "unlocated" | `src/Plugins/Tia/Graph.php` |
| 2 | `enterReplayMode()` uses `activateLinkTracking()` under piggyback coverage | `src/Plugins/Tia.php` |
| 3 | `enterReplayMode()` stamps `TIA_PIGGYBACK_COVERAGE` for workers | `src/Plugins/Tia.php` |
| 4 | `replaceEdges(…, keepExisting:)` — piggyback edges seed empty sets, never overwrite populated ones | `Graph.php`, `Tia.php` |
| 5 | `renderFreshGraph()` stops claiming "fresh graph" when the graph is kept; reason reworded to `recording a coverage baseline` | `Tia.php` |
| 6 | `COVERAGE_REPORT_FLAGS` + `coverageReportActive()` union over `originalArguments`; new `pestCoverageActive()` keeps the coverage-cache marker/hijack on Pest's own `--coverage` | `Tia.php` |
| 7 | `Tia::recordsEdgesInWorkers()` + `WrapperRunner::handleTia()` inject `-d pcov.directory=<root>` into worker argv | `Tia.php`, `src/Plugins/Parallel/Paratest/WrapperRunner.php` |
| 8 | Sequential record runs announce structural drift via `renderFreshGraph()` | `Tia.php` |
| 9 | `Graph::getTime()` + `cachedTimeByTestId` + `resultTime()` preserve replayed durations; edge-less write guard is now `$recordsEdges = $complete && ($markKnownTestFiles \|\| $this->recordingActive)` | `Graph.php`, `Tia.php` |

Deliberately **not** done: `PLAN.md` §5 SIGINT propagation, §5 warning/deprecation `status=0`
mapping, and all of §6 (playground annotation fixtures). The vacuous C rows below stay vacuous.

### Target-outcome changes this forces

Two rows in the original matrix asserted the **old**, buggy behaviour. Their targets are updated
below — do not report them as regressions:

- **B10** was "`time` differs; statuses stable". Change 9 means replayed entries now **keep** their
  recorded `time`. New target: `time` differs only for tests that actually executed.
- **B12** gains an edge-preservation assertion it never had (see K1).

### One known-failing repo test

`tests/Unit/Plugins/Tia/Graph.php:69-76` asserts `hasUnlocatedTestsToRerun('main')` is `false` for
`tests/Feature/FooTest.php` under `new Graph(sys_get_temp_dir())` — a path that does not exist. That
assertion encodes the I9 bug and **will fail** under change 1. It needs re-pointing at a root/file
that exists (e.g. `dirname(__DIR__, 4)` + `'tests/Unit/Plugins/Tia/Graph.php'`). Left untouched by
request; it is a repo-test matter, not a playground one.

---

## Part 1 — Environment, and the traps in it

**Playground:** `/Users/nunomaduro/Work/projects/playground/laravel`

```bash
cd /Users/nunomaduro/Work/projects/playground/laravel
GRAPH="$(PAO_DISABLE=1 ./vendor/bin/pest --baseline)/graph.json"   # ~/.pest/tia/laravel-4a455a95622ac0ec
```

1. **Every** pest invocation needs `PAO_DISABLE=1` — the app has `laravel/pao`, which emits JSON
   when it detects an agent.
2. **Never run `git checkout .` or `git checkout -- composer.lock` in the playground.** It has four
   pre-existing user-modified files — `AGENTS.md`, `CLAUDE.md`, `composer.json`, `composer.lock` —
   that a blanket reset would destroy. `PLAN.md`'s "reset ritual" is unsafe as written. Scope resets
   to what you touched: `git checkout -- tests app` / `git clean -fd tests app`, and for
   `composer.lock` (needed for the drift rows) **copy it aside and copy it back**, verifying with
   `shasum`.
3. **`vendor/pestphp/pest` is a dist copy, not a symlink.** Composer installed
   `dev-fix/tia-filtered as 5.2.0`, so edits in the pest repo do **not** reach the playground.

   **Tell Nuno whenever a sync is needed to move forward — do not sync silently.** Say what is stale
   and what the sync would be, then wait. This includes temporarily swapping in `db70017c` files for a
   before/after contrast. When you report any playground result, state which commit produced it.
   The sync itself, once he agrees:
   ```bash
   PEST=/Users/nunomaduro/Work/projects/pestphp/pest
   V=/Users/nunomaduro/Work/projects/playground/laravel/vendor/pestphp/pest
   for f in src/Plugins/Tia.php src/Plugins/Tia/Graph.php \
            src/Plugins/Parallel/Paratest/WrapperRunner.php; do cp "$PEST/$f" "$V/$f"; done
   ```
   Verify with `grep -c recordsEdgesInWorkers "$V/src/Plugins/Tia.php"` → `1`. **Always restore the
   current version before continuing** after a pre-fix contrast.
4. **Coverage driver:** pcov only, no xdebug. `ini_get('pcov.directory')` is `''` by default — that
   emptiness is the entire mechanism behind G12.
5. **Suite shape as found:** 7 tests in 5 files — `tests/Unit/{CalculatorTest,ExampleTest,GreeterTest,
   TrioTest}.php`, `tests/Feature/ExampleTest.php`. A healthy sequential graph is **`files=22`,
   5 edge keys, self-edge on every test** (`PLAN.md`'s `files=25` is stale). Pre-fix parallel gives
   `files=4` with zero self-edges. **These numbers shift the moment you add the Part 1b fixtures** —
   re-derive them once, after the fixtures land, and use the new numbers throughout. The invariants
   that do *not* shift: sequential and parallel must agree, and every test must have a self-edge.
6. **Sentinel patching is the only reliable discriminator** between "wrote identical values" and
   "wrote nothing": rewrite every cached result to `time=9.999 assertions=42`, snapshot, run the
   case, diff. A canary test absent from `edges` is unreliable under `--filter` because it never
   matches the filter and so never runs.
7. Seeding a cached failure needs `--fresh` (or an env-driven flaky fixture): `--tia` on a clean
   green tree replays rather than executes, so it can never cache a failure. Working recipe — break
   an assertion, `pest --tia --fresh`, then restore the source.
8. Comment-only edits to a test file are **not** changes (AST-level hashing). Use semantic edits.
9. The shell is zsh — build commands with arrays or `eval`; unquoted `$args` does not word-split.

### Graph summariser

Write this to a scratch path and use it for every diff.

```php
<?php // summarise.php <graph.json> [label]
$g = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$files = $g['files'] ?? []; $edges = $g['edges'] ?? [];
echo ($argv[2] ?? $argv[1])."\n  files=".count($files).'  edges keys='.count($edges)."\n";
$dirs = []; foreach ($files as $f) { $dirs[explode('/', (string) $f)[0]] = true; }
ksort($dirs); echo '  file dirs: '.implode(', ', array_keys($dirs))."\n";
ksort($edges);
foreach ($edges as $test => $ids) {
    $self = 'no';
    foreach ((array) $ids as $id) { if (($files[$id] ?? null) === $test) { $self = 'YES'; break; } }
    echo sprintf("  %-42s n=%-3d self=%s\n", $test, count((array) $ids), $self);
}
foreach ($g['baselines'] ?? [] as $branch => $b) {
    $r = $b['results'] ?? [];
    echo "  baseline[$branch]: n=".count($r).' sha='.substr((string) ($b['sha'] ?? '-'), 0, 7)."\n";
    foreach ($r as $id => $x) {
        echo sprintf("    %-58s status=%d time=%s asserts=%d file=%s\n", substr((string) $id, -58),
            $x['status'], $x['time'], $x['assertions'], $x['file'] ?? '-');
    }
}
```

Normalised edge-set equality (for G12 / I5 / K1):

```php
<?php // edgediff.php <a.json> <b.json>
function edges(string $p): array {
    $g = json_decode((string) file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); $out = [];
    foreach ($g['edges'] as $t => $ids) { $s = array_map(fn ($i) => $g['files'][$i], $ids); sort($s); $out[$t] = $s; }
    ksort($out); return $out;
}
$a = edges($argv[1]); $b = edges($argv[2]);
echo $a === $b ? "IDENTICAL edge sets\n" : "DIFFER\n";
foreach ($a as $t => $s) {
    $m = array_diff($s, $b[$t] ?? []); $e = array_diff($b[$t] ?? [], $s);
    if ($m || $e) printf("  %s: -%d +%d\n", $t, count($m), count($e));
}
```

### Per-case loop

```bash
git checkout -- tests app 2>/dev/null; git clean -qfd tests app
rm -rf "$(PAO_DISABLE=1 ./vendor/bin/pest --baseline)"
PAO_DISABLE=1 ./vendor/bin/pest --tia >/dev/null 2>&1     # seed a healthy graph
# sentinel-patch every result to time=9.999 assertions=42, snapshot, run the case, diff
```

---

## Part 1b — Fixtures you must build first

**The playground has none of the fixtures the matrix depends on.** Verified inventory: the only test
files are `tests/Unit/{CalculatorTest,ExampleTest,GreeterTest,TrioTest}.php` and
`tests/Feature/ExampleTest.php` — 7 plain tests, zero occurrences of `->group()`, `->only()`,
`->skip()`, `->todo()`, `->note()`, `->flaky()`, `->covers()`, `->uses()`, `->issue()`, `->pr()`,
`->ticket()`, `->assignee()`, datasets, or any `FLAKY_OK`-style env hook. `phpunit.xml` defines only
the `Unit` and `Feature` testsuites.

So roughly 30 rows below cannot run as written until you create the fixtures. Build them **all up
front, in one commit**, then re-derive the baseline graph shape once and use those numbers for the
whole sweep — every fixture you add changes `files`, the `edges` key count and `n`, so adding them
piecemeal invalidates earlier rows.

| Fixture to create | Rows that need it |
|---|---|
| a test with `->group('smoke')` | C7, C8, C9, C10 |
| an env-driven flaky test (passes iff `FLAKY_OK=1`) | E1, E2, E3, E4 |
| a `->skip()`ed test | E9, D8 |
| a `->todo()` test | E10, C23, C39 |
| a risky test (no assertions; pair with `--disallow-test-output`) | E11, D7 |
| a test that triggers a PHPUnit warning | D6 |
| a test calling `markTestIncomplete()` | D9 |
| a test that triggers a deprecation | D10 |
| a dataset test with ≥3 rows | E13, E14 |
| `->only()` — added and removed per case, not left in | C31, C32, G7 |
| `->covers(App\Services\Calculator::class)` | C18 |
| `->uses(...)` / `UsesClass` annotation | C19 |
| `->note(...)` | C24 |
| `->flaky()` **annotation** (distinct from the env-driven flaky test above) | C25 |
| `->issue(123)`, `->pr(1)`, `->ticket('X')`, `->assignee('X')` | C26, C27, C28, C37, C38 |

This is `PLAN.md` §6's "test-harness gaps to close before re-running", now itemised: without these,
the listed rows' graph-invariant assertions match zero tests and **prove nothing** — they pass
vacuously. Any row still marked "(vacuous)" in Part 2 is vacuous *only because* its fixture is
missing; once you add the fixture, treat the row as unverified and make it load-bearing.

Rows needing an *action* rather than a fixture — breaking `trio one` for the D rows, an uncommitted
test edit for `--dirty`, branch renames and a non-git dir for H5–H10, `--mutate` against
`app/Services` for the F rows — are fine as written; `pestphp/pest-plugin-mutate` is installed.

---

## Part 2 — Full case matrix

Tiers: **COMPLETE** may change everything · **RESULTS-ONLY** (RO) may change only
`baselines[<branch>].results` for tests that ran, and must never remove an entry, add a result for a
test file absent from `edges`, or alter `sha`/`tree`/`edges`/`files`/`fingerprint` ·
**HARD-SUPPRESSED** may change nothing.

Phase-1 column: **VERIFIED** = re-run against the fixed code in the phase-one session, pre-fix
contrast captured · **PASS (sweep)** = passed in the original `db70017` sweep and *not* re-checked
since the changes — these are the bulk of phase two's work · **SKIP** = not runnable as written.

### A — Setup & sanity

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| A1 | `composer show pestphp/pest` | version `dev-fix/tia-filtered` | PASS (sweep) |
| A2 | `pest --baseline` | prints an existing dir; exit 0 | VERIFIED |
| A3 | delete graph, `pest --tia` | graph.json created; one `edges` key per test file; one result per test | VERIFIED |
| A4 | `git status` after reset ritual | clean *except the four user-modified files* (see trap 2) | VERIFIED |
| A5 | `extension_loaded("pcov")` | `true` | VERIFIED |
| A6 | delete graph, plain `pest` | no graph created | PASS (sweep) |
| A7 | delete graph, `pest --filter=adds` | no graph created | PASS (sweep) |
| A8 | two consecutive `pest --tia` | second replays everything; `sha` unchanged | VERIFIED |

### B — COMPLETE runs still write

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| B1 | `pest --tia` (clean) | replays; graph written; `sha` = `git rev-parse HEAD` | VERIFIED |
| B2 | `pest --no-tia` | results refreshed; prune applied; `sha`/`tree`/`edges` unchanged | PASS (sweep) — recheck under change 9 |
| B3 | `pest` (plain) | same as B2 | PASS (sweep) — recheck under change 9 |
| B4 | `pest --tia --fresh` | graph purged and rebuilt; `edges` rebuilt; `files` repopulated | VERIFIED |
| B5 | `pest --bail`, all green | COMPLETE — graph written, prune applied | PASS (sweep) |
| B6 | edit `Calculator.php`, `pest --tia` | `CalculatorTest` re-runs, others replay; `tree` updated | PASS (sweep) |
| B7 | delete `trio three`, `pest --tia` | its result pruned; `trio one`/`two` remain | PASS (sweep) |
| B8 | delete `GreeterTest.php`, `pest --tia` | result and edges survive (missing-file prune is `--fresh`-only) | PASS (sweep) |
| B9 | B8 then `pest --tia --fresh` | entry gone; `files` drops `Greeter.php` too | PASS (sweep) |
| B10 | `pest --tia` twice | **UPDATED TARGET:** `time` differs only for tests that executed; replayed entries keep their recorded `time`; statuses stable | VERIFIED |
| B11 | add a test file, `pest --tia` | new `edges` key **and** new result appear in the **same** run | VERIFIED (regression guard for change 9) |
| B12 | `pest --tia --coverage` | completes; graph written; coverage report prints | VERIFIED |

### C — Selection narrowing → RESULTS-ONLY

All rows: RO invariants hold. "Notice" = `TIA does not apply to partial runs — running the selected tests directly.`

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| C1 | `pest --filter="adds numbers"` | RO; no notice | PASS (sweep) |
| C2 | `pest --tia --filter="adds numbers"` | RO; notice | PASS (sweep) |
| C3 | `pest --filter="trio one"` | RO; `trio two`/`three` byte-identical | PASS (sweep) |
| C4 | `pest --filter="trio"` | RO; all three update; nothing else does | PASS (sweep) |
| C5 | `pest --exclude-filter="Feature"` | RO; no notice; no prune | PASS (sweep) |
| C6 | `pest --tia --exclude-filter="Feature"` | RO; notice | PASS (sweep) |
| C7 | `pest --group=smoke` | RO; no notice | PASS (sweep) |
| C8 | `pest --tia --group=smoke` | RO; notice | PASS (sweep) |
| C9 | `pest --exclude-group=smoke` | RO; no notice; no prune | PASS (sweep) |
| C10 | `pest --tia --exclude-group=smoke` | RO; notice | PASS (sweep) |
| C11 | `pest tests/Unit` | RO; no notice; Feature entry not pruned | PASS (sweep) |
| C12 | `pest --tia tests/Unit` | RO; notice | PASS (sweep) |
| C13 | `pest tests/Unit/TrioTest.php` | RO; no notice | PASS (sweep) |
| C14 | `pest --tia tests/Unit/TrioTest.php` | RO; notice | PASS (sweep) |
| C15 | `pest --testsuite=Unit` | RO; no notice | PASS (sweep) |
| C16 | `pest --tia --testsuite=Unit` | RO; notice | PASS (sweep) |
| C17 | `pest --tia --exclude-testsuite=Feature` | RO; notice | PASS (sweep) |
| C18 | `pest --tia --covers='App\Services\Calculator'` | RO; notice | PASS (sweep) |
| C19 | `pest --tia --uses='App\Services\Calculator'` | RO; notice | PASS (vacuous — needs fixture) |
| C20 | `pest --tia --test-suffix=Test.php` | RO even though all tests run; `edges`/`files`/`n` unchanged | PASS (sweep) |
| C21 | `pest --dirty` with an uncommitted test edit | RO; no notice | PASS (sweep) |
| C22 | `pest --tia --dirty` | RO; notice | PASS (sweep) |
| C23 | `pest --tia --todos` | RO; notice | PASS (vacuous) |
| C24 | `pest --tia --notes` | RO; notice | PASS (vacuous) |
| C25 | `pest --tia --flaky` | RO; notice | PASS (vacuous) |
| C26 | `pest --tia --issue=123` | RO; notice | PASS (vacuous) |
| C27 | `pest --tia --pr=1` | RO; notice | PASS (vacuous) |
| C28 | `pest --tia --pull-request=1` | RO; notice | PASS (vacuous) |
| C29 | `pest --tia --shard=1/2` | RO; notice; no prune | PASS (sweep) |
| C30 | `pest --tia --shard=2/2` | RO; notice; C29+C30 covers the suite; neither prunes | PASS (sweep) |
| C31 | `->only()` on `trio one`, `pest --tia` | RO; no notice; siblings untouched | PASS (sweep) |
| C32 | `->only()` on `trio one`, plain `pest` | RO; no notice | PASS (sweep) |
| C33 | `pest --tia --filtered --filter=adds` | RO; notice; filtered yields to narrowing | PASS (sweep) |
| C34 | `PEST_TIA=1 pest --filter=adds` | RO; notice | PASS (sweep) |
| C35 | `PEST_TIA_FILTERED=1 pest --filter=adds` | RO; notice (regression guard) | PASS (sweep) |
| C36 | `pest --filtered --filter=adds` | RO; notice | PASS (sweep) |
| C37 | `pest --tia --ticket=X` | RO; notice. `--ticket` **is** a real option (`src/Plugins/Snapshot.php:83`) — it narrows legitimately | PASS (vacuous) |
| C38 | `pest --tia --assignee=X` | RO; notice (`src/Plugins/Snapshot.php:81`) | PASS (vacuous) |
| C39 | `pest --tia --todo` | RO; notice; matches nothing | PASS (vacuous) |

### D — Truncation → RESULTS-ONLY

D1–D5, D11–D13, D16 precondition: `trio one` broken. D6–D10 run against a green suite so the `--stop-on-*` trigger is the only narrowing.

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| D1 | `pest --bail` | RO; `trio one` = `status=7`; siblings survive unchanged | PASS (sweep) |
| D2 | `pest --retry` | RO; siblings survive | PASS (sweep) |
| D3 | `pest --stop-on-failure` | RO | PASS (sweep) |
| D4 | `pest --stop-on-defect` | RO | PASS (sweep) |
| D5 | `pest --stop-on-error` (no error occurs) | COMPLETE — nothing stopped it | PASS (sweep) |
| D6 | `pest --stop-on-warning` | RO when it fires. Warning stored as `status=0`, not `6` | PASS (sweep) |
| D7 | `pest --stop-on-risky --disallow-test-output` | RO; `status=5` | PASS (sweep) |
| D8 | `pest --stop-on-skipped` | RO; `status=1` | PASS (sweep) |
| D9 | `pest --stop-on-incomplete` | RO; `status=2` | PASS (sweep) |
| D10 | `pest --stop-on-deprecation` | RO. Deprecation stored as `status=0`, not `4` | PASS (sweep) |
| D11 | `pest --tia --bail` | RO | PASS (sweep) |
| D12 | `pest --bail --filter=trio` | RO (narrowed *and* truncated) | PASS (sweep) |
| D13 | `stopOnFailure="true"` in `phpunit.xml`, plain `pest` | RO — caught via `stoppedEarly()`, not flag matching | PASS (sweep) |
| D14 | D13 config, green suite | COMPLETE | PASS (sweep) |
| D15 | `pest --bail`, last test in run order fails | RO — over-conservative but safe; nothing was skipped | PASS (sweep) |
| D16 | `pest --tia --filtered --bail`, broken affected test | RO | PASS (sweep) |

### E — Result merge semantics

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| E1 | cached `flaky`=7, `FLAKY_OK=1 pest --filter=flaky` | flips to `status=0`; all other results byte-identical | PASS (sweep) |
| E2 | cached `flaky`=0, `pest --filter=flaky` (env unset) | flips to `status=7` | PASS (sweep) |
| E3 | after E1, `pest --tia --filtered` clean | `No affected tests found`; zero graph delta | PASS (sweep) |
| E4 | after E2, `pest --tia --filtered` | re-runs, `from 1 previously unsuccessful test` | PASS (sweep) |
| E5 | any partial run | `assertions` and `time` update for the test that ran (it executed, so change 9 does not apply) | PASS (sweep) |
| E6 | any partial run | `message` of untouched tests unchanged | PASS (sweep) |
| E7 | two sequential partial runs on different tests | each updates only its own entry; both persist | PASS (sweep) |
| E8 | `pest --filter="trio one"` | siblings keep exact `status`/`time`/`assertions`/`message` | PASS (sweep) |
| E9 | partial run of a `->skip()`ed test | `status=1` | PASS (sweep) |
| E10 | partial run of a `->todo()` test | `status=1`, `assertions=0`, `message="__TODO__"` | PASS (sweep) |
| E11 | partial run of a risky test | `status=5` | PASS (sweep) |
| E12 | any partial run | `fingerprint` byte-identical | PASS (sweep) |
| E13 | dataset test, `--filter` matching one row | that row updates; other rows survive (the old prune bug) | PASS (sweep) |
| E14 | dataset test, full `--tia` after deleting a row | the deleted row is pruned | PASS (sweep) |

### F — Guard rails

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| F1 | test absent from `edges`, `pest --filter="brand new"` | no result recorded | VERIFIED |
| F2 | complete `pest --tia` first, then the same filter | result is recorded | VERIFIED |
| F3 | `pest --mutate --path=app/Services --covered-only` | statuses/`edges`/`sha`/`tree`/`files` identical; only `time` may change | PASS (sweep) |
| F4 | `pest --mutate --parallel --path=… --covered-only` | same (observed: zero delta) | PASS (sweep) — recheck under change 7 |
| F5 | weakened test so a mutant survives, `pest --mutate` | no status change in the baseline | PASS (sweep) |
| F6 | `pest --mutate` with the graph deleted | no graph created | PASS (sweep) |
| F7 | mutation subprocess env | carries `PEST_MUTATION_TESTING`; `--mutate` stripped from argv | PASS (sweep) |
| F8 | grep baseline after `--mutate` | no mutation-flavoured messages | PASS (sweep) |

### G — Parallel

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| G1 | `pest --tia --parallel` (clean) | COMPLETE — graph written; all 7 results present | VERIFIED |
| G2 | `pest --tia --parallel --filter=adds` | RO | PASS (sweep) |
| G3 | `pest --parallel --bail`, `trio one` broken | RO; siblings survive | PASS (sweep) |
| G4 | `pest --tia --parallel --bail` | RO | PASS (sweep) |
| G5 | `pest --tia --filtered --parallel` after a source edit | narrows to affected; writes | VERIFIED (see I5) |
| G6 | `pest --tia --parallel` | worker results reach the parent baseline | VERIFIED |
| G7 | `->only()` + `pest --tia --parallel` | RO | PASS (sweep) |
| G8 | `pest --tia --parallel --shard=1/2` | RO | PASS (sweep) |
| G9 | broken test in one worker, `pest --parallel --bail` | whole run is RO, not just that worker's slice | PASS (sweep) |
| G10 | `pest --parallel --retry` | `InvalidOption`; graph untouched | PASS (sweep) |
| G11 | `pest --tia --parallel --fresh` | graph rebuilt | VERIFIED |
| G12 | `edges` after `pest --tia` vs `pest --tia --parallel --fresh` | **equivalent edge sets** — `files=22`, self-edge on all 5 tests, both | **VERIFIED (fixed)** — pre-fix: `files=4`, 0 self-edges |

### H — Baseline key / branch resolution

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| H1 | `pest --tia` on `master` | only a `master` key | VERIFIED |
| H2 | plain `pest` on `master` | writes `master`, not `main` | PASS (sweep) |
| H3 | `pest --bail` truncated on `master` | no `main` key minted | PASS (sweep) |
| H4 | `pest --filter=adds` on `master` | no `main` key minted | PASS (sweep) |
| H5 | `git branch -m main`, full `pest --tia` | single `main` key | PASS (sweep) |
| H6 | `git checkout -b feature/x`, `pest --tia` | `feature/x` key appears; reads fall back to the existing baseline | PASS (sweep) |
| H7 | detached HEAD, `pest --tia` | falls back to the `main` key for both reads and writes; no `HEAD` key | PASS (sweep) |
| H8 | after every C and D case | no baseline key other than the real branch | PASS (sweep) |
| H9 | non-git dir, `pest --tia` | `MissingDependency` — `The feature "Tia mode" requires "git".` | PASS (sweep) |
| H10 | non-git dir, plain `pest` | runs normally; no baseline dir created | PASS (sweep) |

### I — Filtered mode

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| I1 | clean + green, `pest --tia --filtered` | `No affected tests found`; zero graph delta | PASS (sweep) |
| I2 | edit `Calculator.php` | only `CalculatorTest` runs | VERIFIED |
| I3 | cached failure, clean tree | re-runs it, `from 1 previously unsuccessful test` | PASS (sweep) |
| I4 | I2 with sentinels | `tree` updated; exactly one entry rewritten; all others retained | PASS (sweep) |
| I5 | `pest --tia --filtered --parallel` | same narrowing as I2 **and `edges` unchanged** | **VERIFIED (fixed)** — 1 affected test, edges identical |
| I6 | `PEST_TIA_FILTERED=1 pest --tia` | identical delta to I4 | PASS (sweep) |
| I7 | `pest --tia --filtered tests/Unit` | explicit path wins; filtered off; RO; notice | PASS (sweep) |
| I8 | `pest --tia --filtered --coverage-text` | filtered mode disabled by an active coverage report → full suite runs | **VERIFIED (fixed)** — pre-fix: `No affected tests found` |
| I9 | cached failure whose test file was deleted | WARN `Some cached tests due a re-run could not be located on disk` + `Running the full suite with replay instead of a filtered run` | **VERIFIED (fixed)** — pre-fix: `No tests found`, exit 0 green |
| I10 | `pest --tia --filtered` with no baseline yet | records a baseline instead of filtering | PASS (sweep) |
| I11 | edit a Blade view a Feature test renders | the Feature test is selected | PASS (sweep) |
| I12 | edit `composer.lock` | fingerprint drift → full rebuild, **and the reason is printed sequentially**: `fresh graph (composer.lock changed)` | **VERIFIED (fixed)** — pre-fix: bare `Running in TIA mode.` |

### J — Interactions & regressions

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| J1 | `pest --tia --fresh --filter=adds` | graph **not** purged; RO | PASS (sweep) |
| J2 | `pest --tia --refetch --filter=adds` | refetch not performed; RO | PASS (sweep) |
| J3 | `pest --no-tia --filter=adds` | RO — narrowing wins over `--no-tia`; no notice | PASS (sweep) |
| J4 | `pest --tia --no-tia` | TIA disabled; COMPLETE | PASS (sweep) |
| J5 | `<groups><exclude><group>integration</group></exclude></groups>`, `pest --tia` | COMPLETE, no notice — an always-in-force filter applies to baseline runs too | PASS (sweep) |
| J6 | `pest --tia --compact` | COMPLETE | PASS (sweep) |
| J7 | `pest --tia -v` | COMPLETE | PASS (sweep) |
| J8 | `pest --tia --profile` | COMPLETE | PASS (sweep) |
| J9 | `pest --tia --order-by=random` | COMPLETE | PASS (sweep) |
| J10 | `pest --tia --random-order-seed=1234` | COMPLETE | PASS (sweep) |
| J11 | `pest --tia --repeat=2` | COMPLETE — repetition is not narrowing | **SKIP** — `--repeat` is not a Pest option |
| J12 | `pest --filter=adds` twice | only the executed test's `time` differs; rest byte-identical | PASS (sweep) |
| J13 | `pest --tia --min=50` | COMPLETE (silent no-op without `--coverage`) | PASS (sweep) |
| J14 | SIGINT mid-suite | RO — an interrupted run is truncated | PASS (sweep) — signal still does not reach the re-exec'd child (not fixed) |
| J15 | delete the graph, `pest --tia --filtered` | records a baseline rather than erroring | PASS (sweep) |
| J16 | corrupt `graph.json`, `pest --tia` | recovers by rebuilding; no crash | PASS (sweep) |

### K — New rows for the phase-one fixes

These assert behaviour no original row covered. All were verified in phase one; re-run them as
regression guards.

| # | Case | Target outcome | Phase 1 |
|---|---|---|---|
| K1 | healthy `pest --tia` graph, then `pest --tia --coverage` | `edges` **byte-identical** — piggyback data may seed empty sets, never narrow populated ones; `files=22` stays `22` | **VERIFIED (fixed)** — pre-fix: `ExampleTest` 16→2 edges, self-edges lost |
| K2 | same run's headline | `Experimental TIA mode enabled / recording a coverage baseline.` — no false `fresh graph` | **VERIFIED (fixed)** |
| K3 | `pest --tia` ×3 on a clean tree | replayed entries keep their recorded `time` across all three | **VERIFIED (fixed)** — pre-fix: `0.053 → 0.001 → 0.001 → 0.001` |
| K4 | add a test file, one `pest --tia` (graph exists, fingerprint matches → replay+refresh) | result **and** edges appear in that same run; `n` grows by 1 | **VERIFIED** — regression guard for change 9 |
| K5 | add a test file, plain `pest --no-tia` | no result written for it; `n` unchanged; no edges | **VERIFIED (fixed)** — pre-fix: edge-less result inflated `n` |
| K6 | `pest --tia --parallel` worker argv | carries `-d pcov.directory=<projectRoot>`; `PEST_TIA` unset; not re-exec'd | **VERIFIED** — probe `bin/worker.php` |
| K7 | second `pest --tia --coverage` (cache primed → replay) | recorder uses link tracking only, does not clear PHPUnit's data mid-collection; coverage report intact | Not yet measured — **new work** |
| K8 | `pest --tia --parallel --coverage` | workers read `TIA_PIGGYBACK_COVERAGE`; no widened pcov scope; report intact | Not yet measured — **new work** |
| K9 | `pest --tia --filtered --coverage-html=<dir>` / `--coverage-clover=<file>` | filtered mode off, same as I8, for every flag in `COVERAGE_REPORT_FLAGS` | Not yet measured — **new work** |

---

## Part 3 — Priorities for phase two

0. **Part 1b fixtures** — nothing in C, D6–D10, or E9–E14 means anything until they exist.
1. **K7, K8, K9** — the only rows never measured. K7/K8 exercise changes 2 and 3, which were
   reasoned about but not observed; K9 covers the seven `COVERAGE_REPORT_FLAGS` beyond
   `--coverage-text`.
2. **B2, B3, F4** and all of **D** and **E** — change 9 touched the shared result-write path, and
   these are the rows that exercise it hardest. `$recordsEdges` is the thing to falsify: it must be
   false for every partial and every non-recording run.
3. **F3–F8** — change 7 injects a `-d` into worker argv; `--mutate --parallel` is the one place that
   both spawns workers and must write nothing.
4. **H1–H10** — untouched by these changes; cheapest bulk confirmation.
5. Raw PHPUnit coverage flags print **no report at all** in Pest, with or without TIA
   (`--no-tia --coverage-text` is equally silent). Pre-existing, unrelated to change 6 — do not
   chase it as a regression, but it means I8/K9 can only assert the selection half.

Report per row: tier respected (yes/no), the graph delta under sentinel patching, and for any
failure the pre-fix contrast (write `git show db70017c:<file>` into vendor, re-run, restore) so a
regression is told apart from a pre-existing defect.
