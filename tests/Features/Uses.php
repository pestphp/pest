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

function testUsesFunction() {}

it('uses the correct PHPUnit attribute for class', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[1]->getName())->toBe(UsesClass::class);
    expect($attributes[1]->getArguments()[0])->toBe(CoversClass1::class);
})->usesClass(CoversClass1::class);

it('uses the correct PHPUnit attribute for trait', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[2]->getName())->toBe(UsesTrait::class);
    expect($attributes[2]->getArguments()[0])->toBe(CoversTrait1::class);
})->usesTrait(CoversTrait1::class);

it('uses the correct PHPUnit attribute for function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[3]->getName())->toBe(UsesFunction::class);
    expect($attributes[3]->getArguments()[0])->toBe('testUsesFunction');
})->usesFunction('testUsesFunction');

it('guesses if the given argument is a class, trait, or function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[4]->getName())->toBe(UsesClass::class);
    expect($attributes[4]->getArguments()[0])->toBe(CoversClass3::class);

    expect($attributes[5]->getName())->toBe(UsesTrait::class);
    expect($attributes[5]->getArguments()[0])->toBe(CoversTrait1::class);

    expect($attributes[6]->getName())->toBe(UsesFunction::class);
    expect($attributes[6]->getArguments()[0])->toBe('testUsesFunction');
})->uses(CoversClass3::class, CoversTrait1::class, 'testUsesFunction');

it('throws exception if no class, trait, nor method has been found', function () {
    $testCall = new TestCall(TestSuite::getInstance(), 'uses-filename', 'uses-description', fn () => 'closure');

    $testCall->uses('fakeName');
})->throws(InvalidArgumentException::class, 'No class or method named "fakeName" has been found.');
