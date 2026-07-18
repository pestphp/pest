<?php

use Pest\Support\Backtrace;

it('gets file name from called file', function (): void {
    $a = (fn (): string => Backtrace::file());

    expect($a())->toBe(__FILE__);
});
