<?php

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;

test('does not throw when an integer --random-order-seed is passed as a separate argv element', function (): void {
    $arguments = ['--order-by=random', '--random-order-seed', 1782350398];

    expect(Tia::isEnabledForRun($arguments))->toBeFalse();
});

test('still detects --tia when an integer argument is present', function (): void {
    $arguments = ['--tia', '--random-order-seed', 1782350398];

    expect(Tia::isEnabledForRun($arguments))->toBeTrue();
});

test('locally configured tia runs outside CI', function (): void {
    withLocalTia(function (): void {
        expect(Tia::isEnabledForRun([]))->toBeTrue();
    });
});

test('locally configured tia stays off when CI is detected', function (string $variable): void {
    withLocalTia(function () use ($variable): void {
        $previous = getenv($variable);
        putenv($variable.'=true');

        try {
            expect(Tia::isEnabledForRun([]))->toBeFalse()
                ->and(Tia::isEnabledForRun(['--ci']))->toBeFalse();
        } finally {
            putenv($previous === false ? $variable : $variable.'='.$previous);
        }
    });
})->with(['CI', 'GITHUB_ACTIONS']);

function withLocalTia(Closure $callback): void
{
    $patterns = Container::getInstance()->get(WatchPatterns::class);
    $reflection = new ReflectionClass($patterns);
    $enabled = $reflection->getProperty('enabled');
    $locally = $reflection->getProperty('locally');
    $wasEnabled = $enabled->getValue($patterns);
    $wasLocally = $locally->getValue($patterns);

    $patterns->markEnabled();
    $patterns->markLocally();

    try {
        $callback();
    } finally {
        $enabled->setValue($patterns, $wasEnabled);
        $locally->setValue($patterns, $wasLocally);
    }
}
