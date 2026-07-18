<?php

use Pest\Plugins\Tia;

test('does not throw when an integer --random-order-seed is passed as a separate argv element', function (): void {
    // Mirrors the parallel worker argv (see bin/worker.php), where
    // `--random-order-seed` and its value arrive as separate items and the
    // seed value is an int rather than a string. Regression test for the
    // str_starts_with() TypeError in Tia::argumentPresent() — the same class
    // of bug as #1206, which was only fixed in HandleArguments.
    $arguments = ['--order-by=random', '--random-order-seed', 1782350398];

    expect(Tia::isEnabledForRun($arguments))->toBeFalse();
});

test('still detects --tia when an integer argument is present', function (): void {
    $arguments = ['--tia', '--random-order-seed', 1782350398];

    expect(Tia::isEnabledForRun($arguments))->toBeTrue();
});
