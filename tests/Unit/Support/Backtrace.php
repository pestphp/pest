<?php

use Pest\Support\Backtrace;

it('gets file name from called file', function () {
    $a = fn () => Backtrace::file();

    assertEquals(__FILE__, $a());
});
