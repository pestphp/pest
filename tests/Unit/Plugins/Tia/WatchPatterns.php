<?php

use Pest\Plugins\Tia\Configuration;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;

beforeEach(function (): void {
    $this->watchPatterns = new WatchPatterns;

    Container::getInstance()->add(WatchPatterns::class, $this->watchPatterns);
});

afterEach(function (): void {
    Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
});

test('baselined defaults to no custom workflow', function (): void {
    (new Configuration)->baselined();

    expect($this->watchPatterns->isBaselined())->toBeTrue()
        ->and($this->watchPatterns->baselineWorkflow())->toBeNull();
});

test('baselined accepts a custom workflow filename', function (): void {
    (new Configuration)->baselined('ci-baseline.yml');

    expect($this->watchPatterns->isBaselined())->toBeTrue()
        ->and($this->watchPatterns->baselineWorkflow())->toBe('ci-baseline.yml');
});

test('baselined ignores a blank workflow filename', function (): void {
    (new Configuration)->baselined('   ');

    expect($this->watchPatterns->isBaselined())->toBeTrue()
        ->and($this->watchPatterns->baselineWorkflow())->toBeNull();
});

test('reset clears the custom workflow filename', function (): void {
    (new Configuration)->baselined('ci-baseline.yml');

    $this->watchPatterns->reset();

    expect($this->watchPatterns->isBaselined())->toBeFalse()
        ->and($this->watchPatterns->baselineWorkflow())->toBeNull();
});
