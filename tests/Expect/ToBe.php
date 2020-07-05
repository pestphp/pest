<?php

use PHPUnit\Framework\ExpectationFailedException;

test('strict comparisons', function () {
    $nuno = new stdClass();
    $dries = new stdClass();

    expect($nuno)->toBe($nuno);
    expect($nuno)->not->toBe($dries);
});

test('failures', function () {
    expect(1)->toBe(2);
})->throws(ExpectationFailedException::class);

test('not failures', function () {
    expect(1)->not->toBe(1);
})->throws(ExpectationFailedException::class);
