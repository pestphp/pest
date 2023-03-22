<?php

use Pest\Support\Str;

it('evaluates the code', function ($evaluatable, $expected) {
    $code = Str::evaluable($evaluatable);

    expect($code)->toBe($expected);
})->with([
    ['version()', '__pest_evaluable_version__'],
    ['version__ ', '__pest_evaluable_version_____'],
    ['version\\', '__pest_evaluable_version_'],
]);
