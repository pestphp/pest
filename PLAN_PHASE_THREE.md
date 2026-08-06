# TIA default-branch fallback — phase three

## Your task

Fix [pestphp/pest#1823](https://github.com/pestphp/pest/issues/1823) — TIA's cached results never
hit for repos whose default branch is not literally `main` — then re-run the **whole** conformance
matrix against the playground app, plus the new section **L** that covers the fix.

Work in this order, and **stop where the plan says stop**:

1. **Part 1** — read the diagnosis. It is already measured; do not re-derive it, but do re-confirm
   the two reproductions in Part 1.3 take ~2 minutes and prove your environment is sane.
2. **Part 2** — implement the fix in the `pestphp/pest` repo. **Do not commit. Do not touch the
   playground's `vendor/`.**
3. **HARD STOP → Part 3.** Report the diff to Nuno and wait. He validates, commits, and syncs it
   into the playground himself. You must not proceed until he confirms.
4. **Part 4** — verify the sync landed, then run section **L** (new) and the full phase-two matrix
   (**A–K**, all 156 rows) against the playground.
5. **Part 5** — report in the given format.

Per `CLAUDE.md`: **do not write new `pestphp/pest` unit tests and do not run `composer test`.** Make
the change, report it, and ask whether repo tests should be added. The section-L rows are *playground
invocations*, not repo tests — those are the deliverable and are always in scope.

---

## Part 1 — The diagnosis (already measured; commit `bfd5b756`)

### 1.1 Root cause — two independent hardcoded `'main'` literals

**(a) The read fallback.** `src/Plugins/Tia/Graph.php` — seven methods default the fallback to the
literal `'main'`:

| line | method |
|---|---|
| 579 | `recordedAtSha(string $branch, string $fallbackBranch = 'main')` |
| 614 | `getAssertions(…, string $fallbackBranch = 'main')` |
| 625 | `getTime(…, string $fallbackBranch = 'main')` |
| 636 | `getResult(…, string $fallbackBranch = 'main')` |
| 663 | `testFilesToRerun(string $branch, string $fallbackBranch = 'main')` |
| 700 | `hasUnlocatedTestsToRerun(string $branch, string $fallbackBranch = 'main')` |
| 811 | `lastRunTree(string $branch, string $fallbackBranch = 'main')` |

They all funnel into `Graph::baselineFor()` (line 819), which *does* implement a real cross-branch
fallback:

```php
if (isset($this->baselines[$branch]))                                         return $this->baselines[$branch];
if ($branch !== $fallbackBranch && isset($this->baselines[$fallbackBranch]))  return $this->baselines[$fallbackBranch];
return ['sha' => null, 'tree' => [], 'results' => []];
```

The mechanism is deliberate. The problem is that **no caller ever passes `$fallbackBranch`** — all
nine call sites in `src/Plugins/Tia.php` (lines 419, 429, 432, 785, 857, 1026, 1031, 1048, 1056) pass
only `$this->branch`. So on a `master`-named repo the second branch of `baselineFor()` can never
fire, and the whole mechanism is dead code.

`grep -rniE 'defaultBranch|symbolic-ref|init\.defaultBranch|origin/HEAD' src/` returns **nothing** —
no default-branch resolution exists anywhere.

**(b) The detached-HEAD default.** `src/Plugins/Tia.php:206` declares `private string $branch = 'main';`
and `ChangedFiles::currentBranch()` (`ChangedFiles.php:208`) returns `null` for detached HEAD. So on
detached HEAD `$this->branch` stays the literal `'main'` and is used for **both reads and writes** —
minting a baseline key for a branch that does not exist. This is a *write*-side bug and a separate fix
from (a).

### 1.2 Not a regression

`git log -S"fallbackBranch = 'main'"` bottoms out at `c7e32f5d feat(tia): continues to work on poc`.
This is original PoC code, untouched by phase one. Phase one's change 1 modified
`hasUnlocatedTestsToRerun()`'s file-existence check — one of the seven methods — without going near
the fallback. Do not report it as a phase-one regression.

No test anywhere exercises the mechanism: `tests/Unit/Plugins/Tia/Graph.php` uses `'main'` as the
*actual* branch name, so those assertions pass whether or not the fallback exists. A branch-name
mismatch is never tested.

### 1.3 The two reproductions (re-confirm these before you start)

Both on the playground, `master`-named default branch, zero local changes:

```
default = master                          default = main
1. record on default   → full run (cold)  → full run (cold)
2. 1st run on feature-x → 25 UNCACHED     → 25 replayed   ← (a)
3. 2nd run on feature-x → 25 replayed     → 25 replayed
4. back on default      → 25 replayed     → 25 replayed
5. 1st run on feature-y → 25 UNCACHED     → 25 replayed
```

```
master-only graph, then `git checkout --detach`, then `pest --tia`:
  → 25 uncached, and keys become [master,main]   ← (b) spurious key
```

The cost is **one full run per new branch, forever**, with no output explaining why — the only clue
is the `N uncached` count; the headline is just `─ Experimental TIA mode enabled.`

### 1.4 Correction to phase two

Phase two reported **H6 and H7 as passing. Both were false passes.** They ran after H5, which had
renamed `master`→`main` and left a `main` key in the graph, so the hardcoded fallback resolved by
accident. Re-measured against a clean `master`-only graph, both fail. Section L replaces them as the
load-bearing rows; H6/H7 must be re-run **from a cold graph** this time (see Part 4.2).

---

## Part 2 — The fix to implement

### 2.1 Recommended design

**Step 1 — add a non-throwing resolver** to `src/Plugins/Tia/ChangedFiles.php`, next to
`currentBranch()`:

```php
public function defaultBranch(): ?string
```

Resolution order, each step failing soft to the next:

1. `git symbolic-ref --short refs/remotes/origin/HEAD` → strip a leading `origin/`
2. `git config --get init.defaultBranch`
3. `null`

Unlike `currentBranch()`, this must **never throw** `MissingDependency` — it is advisory. Return
`null` on any non-zero exit or empty output.

**Step 2 — add a config surface.** `src/Plugins/Tia/Configuration.php` already exposes `always()`,
`locally()`, `filtered()`, `baselined()`, `watch()`. Add:

```php
public function defaultBranch(string $branch): self
```

so `pest()->tia()->defaultBranch('master')` works in `tests/Pest.php`. Explicit config **always
wins** over autodetection — that is the escape hatch when `origin/HEAD` is unset.

**Step 3 — resolve once, in `Tia.php`.** The read path is hot (`getResult()` is called per test at
line 419), so resolution must not shell out per call. Resolve alongside `$this->branch` at
`Tia.php:1910`, under the existing `$branchResolved` guard:

```php
$this->fallbackBranch = $configuredDefaultBranch
    ?? $changedFiles->defaultBranch()
    ?? 'main';
```

**Step 4 — thread it into `Graph`.** Prefer a `Graph`-level property over editing nine call sites:
add `Graph::setFallbackBranch(string $branch)`, change the seven signatures to
`?string $fallbackBranch = null`, and resolve inside each with
`$fallbackBranch ??= $this->fallbackBranch;`. `baselineFor()` itself needs no change. This keeps the
public signatures backward-compatible and minimises blast radius.

**Step 5 — fix the detached-HEAD write.** `Tia.php:206`'s `= 'main'` default must become the
resolved default branch, so detached HEAD stops minting a phantom key.

### 2.2 Invariants the fix must not break

These are all covered by existing matrix rows — the fix is wrong if any of them moves:

- **Read-only.** The fallback must affect *reads* only. Writes go through `ensureBaseline($branch)`
  and must keep using the real current branch. Otherwise H1–H4/H8 ("no baseline key other than the
  real branch") break.
- **H9** — a non-git dir with `--tia` must still raise
  `MissingDependency: The feature "Tia mode" requires "git".` Adding a soft resolver must not
  swallow that.
- **H10** — plain `pest` in a non-git dir must still run and create no baseline dir.
- **A2/A3** — cold-graph recording unchanged.
- **I1/E3** — clean+green `--tia --filtered` must still be a true zero-delta run.
- Filtered mode reads `testFilesToRerun()` and `hasUnlocatedTestsToRerun()`, so the fallback must
  reach those two as well, not just `getResult()`.

### 2.3 Decisions for Nuno (raise these at the Part 3 stop)

- **D1** — Consult `origin/HEAD` at all? It requires `git remote set-head` and is absent in many CI
  checkouts and all remote-less repos. Config + `init.defaultBranch` only is simpler but helps fewer
  people out of the box. *Recommendation: keep it, first in the chain, since it fails soft.*
- **D2** — Single-key heuristic: if the graph holds exactly one baseline key, use it as the fallback?
  Fixes the issue with zero git calls, but is implicit and surprising when several keys exist.
  *Recommendation: no.*
- **D3** — Should detached HEAD write a baseline at all, or be read-only? Current behaviour mints a
  key. *Recommendation: read-only.*
- **D4** — Should `pest()->tia()->defaultBranch()` validate that the branch exists, or accept any
  string? *Recommendation: accept any string; a nonexistent name degrades to a full run, which is
  safe.*

---

## Part 3 — HARD STOP

When the code is written:

1. Show Nuno the diff (`git -C /Users/nunomaduro/Work/projects/pestphp/pest diff`) and a one-paragraph
   summary of each file's change.
2. Answer/raise the D1–D4 decisions.
3. State explicitly that you have **not** committed and have **not** synced `vendor/`.
4. Ask whether repo unit tests should be added (per `CLAUDE.md`), describing the tests you have in
   mind — do not write them yet.
5. **Wait.** Nuno commits and applies the change to the playground.

**Never sync the playground's `vendor/` yourself.** `vendor/pestphp/pest` there is a dist copy, not a
symlink (composer installed `dev-fix/tia-filtered as 5.2.0`), so pest-repo edits do not reach it. Say
what is stale and wait. This applies to before/after contrasts too.

Once he confirms, verify the sync actually landed before measuring anything:

```bash
V=/Users/nunomaduro/Work/projects/playground/laravel/vendor/pestphp/pest
grep -c 'defaultBranch' "$V/src/Plugins/Tia/ChangedFiles.php"   # must be ≥ 1
for f in src/Plugins/Tia.php src/Plugins/Tia/Graph.php src/Plugins/Tia/ChangedFiles.php \
         src/Plugins/Tia/Configuration.php; do
  diff -q "/Users/nunomaduro/Work/projects/pestphp/pest/$f" "$V/$f" >/dev/null \
    && echo "SAME $f" || echo "STALE $f"
done
```

If anything reports `STALE`, stop and tell him. State in your final report which pest commit produced
the playground numbers.

---

## Part 4 — Measurement

### 4.1 Environment and traps

**Playground:** `/Users/nunomaduro/Work/projects/playground/laravel` (branch `master`).

1. **Every** pest invocation needs `PAO_DISABLE=1` — the app has `laravel/pao`, which emits JSON when
   it detects an agent.
2. **Pin the interpreter to `php85`.** The playground requires PHP `>= 8.4.1` *and* pcov. Locally only
   `php85` (8.5.8) has both — `php84` (8.4.23) has no pcov, and the bare Herd `php` shim has been
   observed drifting to 8.3.32 mid-session, which kills every run in
   `vendor/composer/platform_check.php`. Also put a `php` → `php85` symlink first on `PATH`: the
   `--shard` list-tests probe spawns a subprocess via bare `php`, not `PHP_BINARY`.
3. **Never run `git checkout .` or `git checkout -- composer.lock` in the playground.** It has four
   pre-existing user-modified files — `AGENTS.md`, `CLAUDE.md`, `composer.json`, `composer.lock`. Scope
   resets to `git checkout -- tests app` / `git clean -fd tests app`. For `phpunit.xml` and
   `composer.lock`, copy aside and copy back, verifying with `shasum`.
4. **zsh does not word-split unquoted parameters.** A `$PEST` string containing a space becomes one
   command name. Route every invocation through `eval` (the `pest()` helper below does this).
5. Comment-only edits to a test file are **not** changes (AST-level hashing). Use semantic edits.
6. **The sentinel technique must not falsify `assertions` on zero-assertion tests.** A
   risky/skipped/incomplete status is *derived* from "performed no assertions", so patching those to
   `42` rewrites the status on replay and destroys the discriminator — it shows up as a phantom
   `status 5→0` defect. The `sentinel.php` below only patches `assertions` where it is already
   non-zero. With that, a full replay gives a clean `rewritten=0`.
7. Restore branch state after every L row. Several rows rename or detach; leaving a stray `main` key
   in the graph is exactly what produced phase two's false H6/H7 passes.

### 4.2 Reference numbers

The playground already carries the phase-two fixtures at commit `fb2e77e` — **do not rebuild them.**
A healthy sequential `--tia` graph is:

> **`files=27`, 10 edge keys (one per test file), self-edge on all 10, `n=25` results.**

`sha` will differ once Nuno commits the vendor sync — re-derive it once and use it throughout. The
`files`/`edges`/`n` numbers hold as long as no test fixture changes. Suite shape: 10 test files, 25
tests, including six deliberate status fixtures (skipped, todo, incomplete, risky, warning,
deprecation), a 3-row dataset, a `smoke` group, an env-driven flaky test (green unless
`FLAKY_FAIL=1`), and the annotation set (`covers`/`note`/`flaky`/`issue`/`pr`/`ticket`/`assignee`).

**Re-run H6 and H7 from a cold graph** (`rm -rf` the graph dir, record on `master` only, *then*
branch/detach). Their phase-two results are void.

### 4.3 Harness

Write these to your scratchpad. `$SP` is your own scratchpad dir.

<details>
<summary><code>lib.sh</code></summary>

```bash
#!/bin/zsh
export PAO_DISABLE=1
PG=/Users/nunomaduro/Work/projects/playground/laravel
SP="<your scratchpad>"
PHPBIN="php85"
PEST="$PHPBIN $PG/vendor/bin/pest"
cd "$PG" || exit 1
pest() { eval "$PEST $*"; }          # zsh: no word-splitting, must eval
GRAPHDIR="$(pest --baseline)"
GRAPH="$GRAPHDIR/graph.json"
mkdir -p "$SP/bin" && ln -sf "$(command -v php85)" "$SP/bin/php"
export PATH="$SP/bin:$PATH"          # --shard spawns bare `php`
reset_tree() { git checkout -- tests app 2>/dev/null; git clean -qfd tests app 2>/dev/null; }
seed() { rm -rf "$GRAPHDIR"; pest --tia >/dev/null 2>&1; $PHPBIN "$SP/sentinel.php" "$GRAPH" >/dev/null; cp "$GRAPH" "$SP/before.json"; }
snap() { cp "$GRAPH" "$SP/before.json"; }
delta() { $PHPBIN "$SP/cmp.php" "$SP/before.json" "$GRAPH"; }
keys() { $PHPBIN -r '$g=json_decode(file_get_contents($argv[1]),true);echo "[".implode(",",array_keys($g["baselines"]??[]))."]";' "$GRAPH"; }
tally() { sed -E $'s/\x1b\\[[0-9;]*[a-zA-Z]//g' <"$SP/out.txt" | grep -E 'Tests:' | sed -E 's/^ +//;s/Tests: +//'; }
```
</details>

<details>
<summary><code>sentinel.php</code> — the write discriminator</summary>

```php
<?php // sentinel.php <graph.json>
$p = $argv[1];
$g = json_decode((string) file_get_contents($p), true, 512, JSON_THROW_ON_ERROR);
$n = 0;
foreach ($g['baselines'] ?? [] as $br => $b) {
    foreach (array_keys($b['results'] ?? []) as $id) {
        $g['baselines'][$br]['results'][$id]['time'] = 9.999;
        // Only falsify a non-zero assertion count: risky/skipped/incomplete are
        // DERIVED from "performed no assertions", so patching those to 42 would
        // rewrite the status on replay and destroy the discriminator.
        if ((int) ($b['results'][$id]['assertions'] ?? 0) > 0) {
            $g['baselines'][$br]['results'][$id]['assertions'] = 42;
        }
        $n++;
    }
}
file_put_contents($p, json_encode($g, JSON_THROW_ON_ERROR));
echo "sentinelled $n results\n";
```
</details>

<details>
<summary><code>oneline.php</code> — one compact tier verdict per row</summary>

```php
<?php // oneline.php <before.json> <after.json>
function load(string $p): ?array {
    return is_file($p) ? json_decode((string) file_get_contents($p), true, 512, JSON_THROW_ON_ERROR) : null;
}
$a = load($argv[1]); $b = load($argv[2]);
if ($a === null || $b === null) { echo 'GRAPH '.($b === null ? 'DELETED' : 'CREATED'); exit; }
function edgeSets(array $g): array {
    $out = [];
    foreach ($g['edges'] ?? [] as $t => $ids) { $s = array_map(fn($i) => $g['files'][$i] ?? "?$i", (array) $ids); sort($s); $out[$t] = $s; }
    ksort($out); return $out;
}
$moved = [];
if (edgeSets($a) !== edgeSets($b)) $moved[] = 'edges';
if (($a['files'] ?? []) !== ($b['files'] ?? [])) $moved[] = 'files';
if (($a['fingerprint'] ?? null) !== ($b['fingerprint'] ?? null)) $moved[] = 'fingerprint';
$brA = array_keys($a['baselines'] ?? []); $brB = array_keys($b['baselines'] ?? []);
if ($brA !== $brB) $moved[] = 'branchkeys('.implode('|', $brA).'->'.implode('|', $brB).')';
$add = $rem = $wr = 0; $shaMoved = $treeMoved = false;
foreach ($brB as $br) {
    $ra = $a['baselines'][$br]['results'] ?? []; $rb = $b['baselines'][$br]['results'] ?? [];
    if (($a['baselines'][$br]['sha'] ?? null) !== ($b['baselines'][$br]['sha'] ?? null)) $shaMoved = true;
    if (($a['baselines'][$br]['tree'] ?? null) !== ($b['baselines'][$br]['tree'] ?? null)) $treeMoved = true;
    $add += count(array_diff(array_keys($rb), array_keys($ra)));
    $rem += count(array_diff(array_keys($ra), array_keys($rb)));
    foreach ($ra as $id => $x) {
        if (! isset($rb[$id])) continue;
        foreach (['status','time','assertions','message'] as $f) {
            if (($x[$f] ?? null) !== ($rb[$id][$f] ?? null)) { $wr++; break; }
        }
    }
}
$n = 0; foreach ($brB as $br) $n = max($n, count($b['baselines'][$br]['results'] ?? []));
printf('n=%d w=%-2d +%d -%d %s%s%s', $n, $wr, $add, $rem,
    $moved === [] ? 'struct:ok' : 'STRUCT:'.implode(',', $moved),
    $shaMoved ? ' sha:CHANGED' : '', $treeMoved ? ' tree:chg' : '');
```
</details>

`cmp.php` (verbose per-entry version of the same) and `summarise.php` / `edgediff.php` are in
`PLAN_PHASE_TWO.md` §"Graph summariser" — reuse them for drill-downs. Reading the verdict:

- `w=` — entries actually **written**. Under sentinel patching this is the only reliable way to tell
  "wrote identical values" from "wrote nothing". A full replay must give `w=0`.
- `struct:ok` + `+0 -0` — no prune, no edges/files/fingerprint movement. Required by RESULTS-ONLY.
- `STRUCT:branchkeys(...)` — a new baseline key appeared. For section L this is the headline signal.

### 4.4 Section L — new rows for this fix

Tiers, unchanged from phase two: **COMPLETE** may change everything · **RESULTS-ONLY (RO)** may
change only `baselines[<branch>].results` for tests that ran, and must never remove an entry, add a
result for a test file absent from `edges`, or alter `sha`/`tree`/`edges`/`files`/`fingerprint` ·
**HARD-SUPPRESSED** may change nothing.

Every L row starts from a **cold graph recorded on the named default branch only** — verify
`keys=[<default>]` before branching. Restore branch state afterwards.

| # | Case | Target outcome |
|---|---|---|
| L1 | default `master`, record, `git switch -c feature-x`, `pest --tia`, zero changes | **all 25 replayed** (`w=0`), not `25 uncached`. The headline fix. |
| L2 | as L1 but default `main` | still all replayed — regression guard, this already worked |
| L3 | default `trunk`, then `develop` | replayed for both; the fix must not special-case two names |
| L4 | L1, then a *second* new branch `feature-y` | replayed too — the toll must not return per branch |
| L5 | L1 then edit `app/Services/Calculator.php` on `feature-x` | narrows to the 2 affected files (`CalculatorTest` + `AnnotationsTest`, which `covers` it); the other 17 replay |
| L6 | L1 with `--tia --filtered` | filtered mode reads the fallback too (`testFilesToRerun`, `hasUnlocatedTestsToRerun`) → `No affected tests found`, zero delta |
| L7 | L1 with `--tia --parallel` | fallback works in workers as well as the parent |
| L8 | L1, then `pest --tia` twice on `feature-x` | idempotent; second run also `w=0` |
| L9 | detached HEAD on a `master`-only graph | replays, and **no `main` key minted** — `keys` stays `[master]`. Bug (b). |
| L10 | `pest()->tia()->defaultBranch('master')` in `tests/Pest.php`, repo default renamed away | config wins over autodetect |
| L11 | config set to a nonexistent branch (`defaultBranch('nope')`) | degrades to a full run; no crash, no phantom key |
| L12 | no remote at all (`git remote remove origin` if present) | still resolves (via `init.defaultBranch`) or degrades safely — must not throw |
| L13 | branch name with a slash (`feature/x/y`) | replayed; no key-splitting bugs |
| L14 | L1, then confirm writes | `feature-x` gets its **own** key; the `master` key is **not** written to (fallback is read-only) |
| L15 | git worktree on a new branch (the issue's scenario) | replays from the default-branch baseline |
| L16 | non-git dir, `pest --tia` | still `MissingDependency: The feature "Tia mode" requires "git".` — the soft resolver must not swallow it |
| L17 | non-git dir, plain `pest` | runs normally; no baseline dir created |
| L18 | count `git` subprocesses during one `--tia` run | default-branch resolution is cached, not one call per test. Probe by shimming `git` on `PATH` to a logging wrapper. |

L10–L11 need a `tests/Pest.php` edit — that file is tracked and **outside** the `tests app` reset
scope in practice (it lives in `tests/`, so `git checkout -- tests` does restore it; verify with
`git status` after).

For L16/L17, build a throwaway non-git project — and note the trap that burned phase two: a
**symlinked** `vendor` makes Pest resolve the project root back to the playground (identical baseline
hash), silently invalidating the test. Use a hardlinked copy:

```bash
NG="$SP/nogit"; rm -rf "$NG"; mkdir -p "$NG"
cp -R composer.json composer.lock phpunit.xml artisan tests app bootstrap config routes resources storage "$NG/"
[ -f .env ] && cp .env "$NG/"
cp -Rl vendor "$NG/vendor" || cp -R vendor "$NG/vendor"
```

Confirm the baseline path differs (`nogit-<hash>`, not `laravel-4a455a95622ac0ec`), and delete both
the temp project and its `~/.pest/tia/nogit-*` dir afterwards.

### 4.5 Re-run the phase-two matrix (A–K, 156 rows)

Re-run every row of `PLAN_PHASE_TWO.md` Part 2 against the fixed build. No row's status is trusted
until re-measured — the fix touches `Graph`'s read path, which nearly every row exercises. Sections
**A, B, H, I** are the load-bearing ones here (H is branch-key resolution; I is filtered mode; both
consume the changed methods directly). **H6 and H7 must be re-derived from a cold graph** (Part 1.4).

Most rows batch cheaply — phase two ran C1–C20 in one call at roughly one line of output each. Use
`oneline.php` for the sweep and `cmp.php` only to drill into anomalies.

### 4.6 Known pre-existing failures — do not report as regressions

| item | status |
|---|---|
| ~~**G4 / G4b** — parallel replay clobbers cached `time` on all non-executed tests.~~ | **Struck in phase four — does not reproduce.** `flushWorkerReplay()` applies `resultTime()` worker-side before writing the partial, so the parent's verbatim read of `$result['time']` is reading values that were already corrected. Pinned by `a parallel replay keeps the recorded time of tests that did not run` (`tests/Features/Tia/CompleteRunWriteTier.php`), which sentinels every cached `time` and asserts a parallel replay writes nothing. |
| **C19** — `--tia --uses=…` cannot be fixtured. TIA hard-errors on PHPUnit classes (`EnsureTiaIsRunningPestTestsOnly`), and Pest has no chainable `->uses()`. | **Expected behaviour per Nuno.** Verify the tier (`w=0`, RO, notice) and move on. Not a defect. |
| **J11** — `--repeat` is not a Pest option (`Unknown option "--repeat"`). | **Don't care per Nuno.** Mark SKIP. |
| **J10** — `--random-order-seed` alone exits 1 with a WARN. Identical without `--tia`. | Pre-existing Pest behaviour, unrelated. Tier still holds. |

---

## Part 5 — Reporting

Per row: **tier respected (yes/no)**, the **graph delta** under sentinel patching, and for any failure
the **pre-fix contrast** so a regression is told apart from a pre-existing defect. For a pre-fix
contrast you need `db70017c` (or `bfd5b756` for "before phase three") files swapped into the
playground's `vendor/` — that is a sync, so **ask Nuno first** and always restore afterwards.

State which pest commit produced the numbers. Close with:

1. Whether L1–L18 all pass (the fix works).
2. Whether A–K regressed anywhere relative to phase two's 154/156.
3. The D1–D4 decisions as implemented.
4. Anything still open — including G4, which will still be failing.

Leave the playground on `master` with only the four user-modified files dirty, no stray branches, and
no leftover `~/.pest/tia/*` dirs beyond `laravel-4a455a95622ac0ec`.
