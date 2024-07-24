<?php

use PHPUnit\Framework\ExpectationFailedException;

expect(true)->toBeTrue()->and(false)->toBeFalse();

test('strict comparisons', function () {
    $nuno = new stdClass;
    $dries = new stdClass;

    expect($nuno)->toBe($nuno)->not->toBe($dries);
});

test('failures', function () {
    expect(1)->toBe(2);
})->throws(ExpectationFailedException::class);

test('failures with custom message', function () {
    expect(1)->toBe(2, 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function () {
    expect(1)->not->toBe(1);
})->throws(ExpectationFailedException::class);
