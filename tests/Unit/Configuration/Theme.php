<?php

use Pest\Configuration\Printer;

it('creates a printer instance', function () {
    $theme = pest()->printer();

    expect($theme)->toBeInstanceOf(Printer::class);
});
