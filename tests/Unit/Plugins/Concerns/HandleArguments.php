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

test('popArgument preserves duplicate values when removing a missing argument', function () {
    $obj = new class
    {
        use HandleArguments;
    };

    $arguments = ['--verbose', '--exclude-group', 'firstGroup', '--exclude-group', 'secondGroup', '--filter=MyTest'];
    $result = $obj->popArgument('--missingitem', $arguments);

    expect($result)->toBe($arguments);
});

test('popArgument preserves duplicate values when removing an existing argument', function () {
    $obj = new class
    {
        use HandleArguments;
    };

    $arguments = ['--verbose', '--exclude-group', 'firstGroup', '--exclude-group', 'secondGroup', '--filter=MyTest'];
    $result = $obj->popArgument('--verbose', $arguments);

    expect($result)->toBe(['--exclude-group', 'firstGroup', '--exclude-group', 'secondGroup', '--filter=MyTest']);
});
