<?php

use Pest\Plugins\Tia;

test('does not throw when an integer --random-order-seed is passed as a separate argv element', function (): void {
    $arguments = ['--order-by=random', '--random-order-seed', 1782350398];

    expect(Tia::isEnabledForRun($arguments))->toBeFalse();
});

test('still detects --tia when an integer argument is present', function (): void {
    $arguments = ['--tia', '--random-order-seed', 1782350398];

    expect(Tia::isEnabledForRun($arguments))->toBeTrue();
});
