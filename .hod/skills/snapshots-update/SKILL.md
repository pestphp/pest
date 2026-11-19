---
name: snapshots-update
description: "Write each snapshot of this project again, then run the integration suite until it passes. Use when the user asks to update the snapshots or when a test of `tests/Visual/` fails."
disable-model-invocation: true
---

# Snapshots

Write each snapshot of this project again, then run `composer test:integration` until it passes.

Use the output from `tests/Fixtures/Suites/SuccessfulRun` when you update the success snapshot or the parallel totals.

## 1. Read the working tree

Run `git status --short`. Name each file that carries a change, then continue with that change in place, because the user starts this skill in the middle of a change.

Write no commit and no tag during this work. Leave each change in the working tree.

## 2. Turn off the coverage driver

Put `XDEBUG_MODE=off` in front of each command of this skill. `.github/workflows/tests.yml` gives `coverage: none` to each job, thus a run of this project on GitHub collects no coverage.

Run `php -m | grep -iE "xdebug|pcov"` and read the name of each coverage driver of this computer. `XDEBUG_MODE=off` turns off `xdebug`. Add `-d pcov.enabled=0` to `php` for `pcov`, and run the command of the composer script `update:snapshots` directly for that flag.

## 3. Write the summary of the parallel run

Run this command with a timeout of 900 seconds:

```
XDEBUG_MODE=off COLLISION_PRINTER=DefaultPrinter COLLISION_IGNORE_DURATION=true PAO_DISABLE=1 php bin/pest --parallel --processes=3 tests/Fixtures/Suites/SuccessfulRun
```

Copy the `Tests:` line into the test `parallel` in `tests/Visual/Parallel.php` if the fixture totals differ.

## 4. Write each snapshot again

Run `XDEBUG_MODE=off composer update:snapshots` with a timeout of 1800 seconds. The command runs `REBUILD_SNAPSHOTS=true php bin/pest --update-snapshots --exclude-group=tia`, and it writes `tests/.snapshots/success.txt` and each file under `tests/.pest/snapshots/`. The command starts a full suite inside a test of `tests/Visual/`, thus the command needs some minutes.

The command exits with the code 2 after a correct run. Read the output and accept these three results:

- a `FAILED` test of `tests/Visual/`, because the test reads the old snapshot and writes the new snapshot in the same run.
- a `FAILED` test of `tests/Features/Flaky.php`, because `--update-snapshots` writes each snapshot and a flaky test stops the retry when a snapshot changes.
- a `RISKY` test that performs no assertion, because a test that only writes a snapshot asserts nothing.

Run `XDEBUG_MODE=off composer test:unit` for a `FAILED` test outside `tests/Visual/` and outside `tests/Features/Flaky.php`. That command writes no snapshot, thus it separates a regression of the code from an effect of `--update-snapshots`. Stop when the test fails again, give the report of section 6, and write no snapshot again.

## 5. Run the integration suite

Run `XDEBUG_MODE=off composer test:integration` with a timeout of 3600 seconds.

Read each test that failed, then take one action.

| Test that failed | Action |
| --- | --- |
| the test `parallel` of `tests/Visual/Parallel.php` | Return to section 3 to check the fixture totals. |
| a different test of `tests/Visual/` | Check the output before you repeat section 4. |
| a test outside `tests/Visual/` | Stop. Give the report of section 6, and write no snapshot again. |

Return to section 3 two times at the most. Give the report of section 6 after the last run, and name each test that failed in that run.

## 6. Report

Give four items:

- each file that this work changed, from `git status --short`.
- the summary that `tests/Visual/Parallel.php` holds now, and the summary that the file held before this work.
- the result of the last run of `composer test:integration`, and the name of each test that failed in that run.
- each section that you repeated, and the reason.
