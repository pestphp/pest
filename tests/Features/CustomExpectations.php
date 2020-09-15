<?php

it ('calls a custom expectation', function () {
    $foo = "foo";
    expect($foo)->toBeFoo();
});

it ('calls a custom expectation with not', function () {
    $bar = "bar";
    expect($bar)->not->toBeFoo();
});

it ('calls a custom expectation with a parameter', function () {
    $foo = "foo";
    expect($foo)->toBeSomething("foo");
});

it ('fails with an invalid custom expectation', function () {
    $foo = "foo";
    expect($foo)->toNonexistentExpectation();
})->throws(\InvalidArgumentException::class, "Could not find expectation for toNonexistentExpectation");
