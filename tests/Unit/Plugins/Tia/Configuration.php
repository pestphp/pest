<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Configuration;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;

afterEach(function (): void {
    Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
});

it('configures the fallback branch', function (): void {
    $watchPatterns = new WatchPatterns;
    Container::getInstance()->add(WatchPatterns::class, $watchPatterns);

    $configuration = new Configuration;

    expect($watchPatterns->fallbackBranch())->toBe('main')
        ->and($configuration->fallbackBranch('master'))->toBe($configuration)
        ->and($watchPatterns->fallbackBranch())->toBe('master');
});
