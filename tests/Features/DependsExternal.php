<?php

test('dependsExternal', function () {
    expect(func_get_args())->toBe(['first', 'second']);
})->dependsExternal('Tests\Features\Depends', 'first', 'second');

test('dependsExternal chained', function () {
    expect(func_get_args())->toBe(['first', 'second']);
})->dependsExternal('Tests\Features\Depends', 'first')
    ->dependsExternal('Tests\Features\Depends', 'second');

test('dependsExternal chained, reversed', function () {
    expect(func_get_args())->toBe(['second', 'first']);
})->dependsExternal('Tests\Features\Depends', 'second')
    ->dependsExternal('Tests\Features\Depends', 'first');

test('depends on tests in an external file', function (string $first, string $second) {
    expect($first)->toBe('first');
    expect($second)->toBe('second');
})->dependsExternal('Tests\Features\Depends', 'first', 'second');

test('depends on tests in an external file with spread arguments', function (string ...$params) {
    expect(func_get_args())->toBe($params);
    expect($params)->toBe(['first', 'second']);
})->dependsExternal('Tests\Features\Depends', 'first', 'second');

test('depends on tests in an external file with defined arguments', function (string $first, string $second) {
    expect($first)->toBe('first');
    expect($second)->toBe('second');
})->dependsExternal('Tests\Features\Depends', 'first', 'second');
