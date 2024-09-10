<?php

it('creates a printer instance', function () {
    $theme = pest()->printer();

    expect($theme)->toBeInstanceOf(Pest\Configuration\Printer::class);
});
