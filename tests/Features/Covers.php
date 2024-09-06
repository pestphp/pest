<?php

use Pest\PendingCalls\TestCall;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Tests\Fixtures\Covers\CoversClass1;
use Tests\Fixtures\Covers\CoversClass3;
use Tests\Fixtures\Covers\CoversTrait;

$runCounter = 0;

function testCoversFunction() {}

covers([CoversClass1::class]);

it('uses the correct PHPUnit attribute for class', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[1]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[1]->getArguments()[0])->toBe('Tests\Fixtures\Covers\CoversClass1');
});

it('uses the correct PHPUnit attribute for function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[3]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[3]->getArguments()[0])->toBe('testCoversFunction');
})->coversFunction('testCoversFunction');

it('guesses if the given argument is a class or function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[5]->getName())->toBe(CoversClass::class);
    expect($attributes[5]->getArguments()[0])->toBe(CoversClass3::class);

    expect($attributes[6]->getName())->toBe(CoversFunction::class);
    expect($attributes[6]->getArguments()[0])->toBe('testCoversFunction');
})->covers(CoversClass3::class, 'testCoversFunction');

it('uses the correct PHPUnit attribute for trait', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[8]->getName())->toBe('PHPUnit\Framework\Attributes\CoversTrait');
    expect($attributes[8]->getArguments()[0])->toBe('Tests\Fixtures\Covers\CoversTrait');
})->coversTrait(CoversTrait::class);

it('uses the correct PHPUnit attribute for covers nothing', function () {
    $attributes = (new ReflectionMethod($this, $this->name()))->getAttributes();

    expect($attributes[3]->getName())->toBe('PHPUnit\Framework\Attributes\CoversNothing');
    expect($attributes[3]->getArguments())->toHaveCount(0);
})->coversNothing();

it('throws exception if no class nor method has been found', function () {
    $testCall = new TestCall(TestSuite::getInstance(), 'filename', 'description', fn () => 'closure');

    $testCall->covers('fakeName');
})->throws(InvalidArgumentException::class, 'No class, trait or method named "fakeName" has been found.');
