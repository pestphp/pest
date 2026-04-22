<?php

declare(strict_types=1);

/**
 * tests-tia bootstrap.
 *
 * Pest's automatic `Pest.php` loader scans the configured `testDirectory()`
 * which defaults to `tests/` and is hard to override from a nested suite.
 * So instead of relying on `tests-tia/Pest.php` being found, wire the
 * helpers in via PHPUnit's explicit `bootstrap=` attribute — simpler,
 * no config-search surprises.
 */
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/Support/Sandbox.php';

use Pest\TestsTia\Support\Sandbox;
use Symfony\Component\Process\Process;

// tests-tia exercises the record path end-to-end, which means the
// sandbox PHP must expose a coverage driver (pcov or xdebug with
// coverage mode). Without one, `--tia` records zero edges and every
// scenario assertion fails with a useless "no coverage driver" banner.
// Bail out loudly at bootstrap so the failure mode is obvious.
if (! extension_loaded('pcov') && ! extension_loaded('xdebug')) {
    fwrite(STDERR, "\n");
    fwrite(STDERR, "  \e[30;43m SKIP \e[0m tests-tia requires a coverage driver (pcov or xdebug).\n");
    fwrite(STDERR, "         Install one, then retry:  composer test:tia\n\n");

    // Exit 0 so CI doesn't fail when the driver is genuinely absent —
    // the CI workflow adds pcov explicitly so this branch only fires on
    // dev machines that haven't set one up.
    exit(0);
}

// Pre-warm the shared composer template once, up-front. Without this,
// parallel workers race on first use — whoever hits `ensureTemplate()`
// second gets a half-written template. A file-based lock + single
// bootstrap pre-warm sidesteps the problem entirely.
Sandbox::warmTemplate();

/**
 * Runs `$body` inside a fresh sandbox, guaranteeing teardown even when the
 * body throws. Keeps scenario tests tidy — one line per setup + destroy.
 */
function tiaScenario(Closure $body): void
{
    $sandbox = Sandbox::create();

    try {
        $body($sandbox);
    } finally {
        $sandbox->destroy();
    }
}

/**
 * Strip ANSI escapes so assertions are terminal-agnostic.
 */
function tiaOutput(Process $process): string
{
    $output = $process->getOutput().$process->getErrorOutput();

    return preg_replace('/\e\[[0-9;]*m/', '', $output) ?? $output;
}
