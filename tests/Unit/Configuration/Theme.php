<?php

declare(strict_types=1);

use Pest\Configuration\Printer;

it('creates a printer instance', function (): void {
    $theme = pest()->printer();

    expect($theme)->toBeInstanceOf(Printer::class);
});
