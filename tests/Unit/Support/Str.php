<?php

use Pest\Support\Str;

it('evaluates the code', function ($evaluatable, $expected) {
    $code = Str::evaluable($evaluatable);

    expect($code)->toBe($expected);
})->with([
    ['version()', 'version__'],
    ['version__ ', 'version___'],
    ['version\\', 'version_'],
]);
