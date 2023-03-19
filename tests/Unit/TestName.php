<?php

it('may start with P', function (string $real, string $toBePrinted) {
    $printed = preg_replace('/P\\\/', '', $real, 1);

    expect($printed)->toBe($toBePrinted);
})->with([
    ['P\Tests\BarTest', 'Tests\BarTest'],
    ['P\Packages\Foo', 'Packages\Foo'],
    ['P\PPPackages\Foo', 'PPPackages\Foo'],
    ['PPPackages\Foo', 'PPPackages\Foo'],
    ['PPPackages\Foo', 'PPPackages\Foo'],
]);
