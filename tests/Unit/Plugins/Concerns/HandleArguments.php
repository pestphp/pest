<?php

use Pest\Plugins\Concerns\HandleArguments;

 beforeEach(function () {
    $this->handler = new class
    {
        use HandleArguments;
    };
 });

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

 test('popArgument keeps non-unique array item when called with missing argument', function () {
    $arguments = ['--verbose', '--exclude-group', 'firstGroup', '--exclude-group', 'secondGroup',  '--filter=MyTest'];
    $result = $this->handler->popArgument('--missingitem', $arguments);

    expect($result)->toBe($arguments);
 });

 test('popArgument keeps non-unique array item when called with existing argument', function () {
    $arguments = ['--verbose', '--exclude-group', 'firstGroup', '--exclude-group', 'secondGroup',  '--filter=MyTest'];
    $result = $this->handler->popArgument('--verbose', $arguments);

    expect($result)->toBe(['--exclude-group', 'firstGroup', '--exclude-group', 'secondGroup',  '--filter=MyTest']);
 });