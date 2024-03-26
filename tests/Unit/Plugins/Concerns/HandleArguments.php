<?php

use Pest\Plugins\Concerns\HandleArguments;

test('method hasArgument', function (string $argument, bool $expectedResult) {
    $obj = new class
    {
        use HandleArguments;
    };

    $arguments = [
        '--long-argument',
        'someValue',
        '-a',
        '--with-equal-sign=1337',
    ];

    expect($obj->hasArgument($argument, $arguments))->toBe($expectedResult);
})->with([
    ['--long-argument', true],
    ['-a', true],
    ['--with-equal-sign', true],
    ['someValue', true],
    ['--a', false],
    ['--undefined-argument', false],
]);
