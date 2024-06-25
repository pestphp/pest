<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('pass', function () {
    expect('Pest\Expectations\HigherOrderExpectation')->toUseTrait('Pest\Concerns\Retrievable')
        ->and('Pest\Expectations\EachExpectation')->not->toUseTrait('Pest\Concerns\Retrievable');
});

test('failures', function () {
    expect('Pest\Expectations\EachExpectation')->toUseTrait('Pest\Concerns\Foo');
})->throws(ArchExpectationFailedException::class);

test('not failures', function () {
    expect('Pest\Expectations\HigherOrderExpectation')->not->toUseTrait('Pest\Concerns\Retrievable');
})->throws(ArchExpectationFailedException::class);
