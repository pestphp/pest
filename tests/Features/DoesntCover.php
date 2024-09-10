<?php

use Pest\PendingCalls\TestCall;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\Attributes\UsesTrait;
use Tests\Fixtures\Covers\CoversClass1;
use Tests\Fixtures\Covers\CoversClass3;
use Tests\Fixtures\Covers\CoversTrait1;

$runCounter = 0;

function testDoesntCoverFunction() {}

doesntCover([CoversClass1::class]);

it('uses the correct PHPUnit attribute for class', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[1]->getName())->toBe(UsesClass::class);
    expect($attributes[1]->getArguments()[0])->toBe(CoversClass1::class);
});

it('uses the correct PHPUnit attribute for trait', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[3]->getName())->toBe(UsesTrait::class);
    expect($attributes[3]->getArguments()[0])->toBe(CoversTrait1::class);
})->doesntCoverTrait(CoversTrait1::class);

it('uses the correct PHPUnit attribute for function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[5]->getName())->toBe(UsesFunction::class);
    expect($attributes[5]->getArguments()[0])->toBe('testDoesntCoverFunction');
})->doesntCoverFunction('testDoesntCoverFunction');

it('guesses if the given argument is a class, trait, or function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[7]->getName())->toBe(UsesClass::class);
    expect($attributes[7]->getArguments()[0])->toBe(CoversClass3::class);

    expect($attributes[8]->getName())->toBe(UsesTrait::class);
    expect($attributes[8]->getArguments()[0])->toBe(CoversTrait1::class);

    expect($attributes[9]->getName())->toBe(UsesFunction::class);
    expect($attributes[9]->getArguments()[0])->toBe('testDoesntCoverFunction');
})->doesntCover(CoversClass3::class, CoversTrait1::class, 'testDoesntCoverFunction');

it('throws exception if no class, trait, nor method has been found', function () {
    $testCall = new TestCall(TestSuite::getInstance(), 'uses-filename', 'uses-description', fn () => 'closure');

    $testCall->doesntCover('fakeName');
})->throws(InvalidArgumentException::class, 'No class, trait or method named "fakeName" has been found.');
