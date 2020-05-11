<?php

use NunoMaduro\Collision\Adapters\Phpunit\Printer;
use Pest\Actions\AddsDefaults;
use PHPUnit\TextUI\DefaultResultPrinter;

it('sets defaults', function () {
    $arguments = AddsDefaults::to(['bar' => 'foo']);

    assertInstanceOf(Printer::class, $arguments['printer']);
    assertEquals($arguments['bar'], 'foo');
});

it('does not override options', function () {
    $defaultResultPrinter = new DefaultResultPrinter();

    assertEquals(AddsDefaults::to(['printer' => $defaultResultPrinter]), [
        'printer' => $defaultResultPrinter,
    ]);
});
