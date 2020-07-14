<?php

use NunoMaduro\Collision\Adapters\Phpunit\Printer;
use Pest\Actions\AddsDefaults;
use PHPUnit\TextUI\DefaultResultPrinter;

it('sets defaults', function () {
    $arguments = AddsDefaults::to(['bar' => 'foo']);

    expect($arguments['printer'])->toBeInstanceOf(Printer::class);
    expect($arguments['bar'])->toBe('foo');
});

it('does not override options', function () {
    $defaultResultPrinter = new DefaultResultPrinter();

    expect(AddsDefaults::to(['printer' => $defaultResultPrinter]))->tobe([
        'printer' => $defaultResultPrinter,
    ]);
});
