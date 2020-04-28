<?php

use Pest\Support\Backtrace;

it('gets file name from called file', function () {
    $a = function () {
        return Backtrace::file();
    };

    assertEquals(__FILE__, $a());
});
