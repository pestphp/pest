<?php

use Pest\PendingCalls\TestCall;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\Fixtures\Covers\CoversClass1;
use Tests\Fixtures\Covers\CoversClass3;
use Tests\Fixtures\Covers\CoversTrait1;

$runCounter = 0;

function testCoversFunction() {}

covers([CoversClass1::class]);

it('uses the correct PHPUnit attribute for class', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[1]->getName())->toBe(CoversClass::class);
    expect($attributes[1]->getArguments()[0])->toBe(CoversClass1::class);
});

it('uses the correct PHPUnit attribute for trait', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[3]->getName())->toBe(CoversTrait::class);
    expect($attributes[3]->getArguments()[0])->toBe(CoversTrait1::class);
})->coversTrait(CoversTrait1::class);

it('uses the correct PHPUnit attribute for function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[5]->getName())->toBe(CoversFunction::class);
    expect($attributes[5]->getArguments()[0])->toBe('testCoversFunction');
})->coversFunction('testCoversFunction');

it('guesses if the given argument is a class, trait, or function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[7]->getName())->toBe(CoversClass::class);
    expect($attributes[7]->getArguments()[0])->toBe(CoversClass3::class);

    expect($attributes[8]->getName())->toBe(CoversTrait::class);
    expect($attributes[8]->getArguments()[0])->toBe(CoversTrait1::class);

    expect($attributes[9]->getName())->toBe(CoversFunction::class);
    expect($attributes[9]->getArguments()[0])->toBe('testCoversFunction');
})->covers(CoversClass3::class, CoversTrait1::class, 'testCoversFunction');

it('uses the correct PHPUnit attribute for covers nothing', function () {
    $attributes = (new ReflectionMethod($this, $this->name()))->getAttributes();

    expect($attributes[3]->getName())->toBe(CoversNothing::class);
    expect($attributes[3]->getArguments())->toHaveCount(0);
})->coversNothing();

it('throws exception if no class, trait, nor method has been found', function () {
    $testCall = new TestCall(TestSuite::getInstance(), 'covers-filename', 'covers-description', fn () => 'closure');

    $testCall->covers('fakeName');
})->throws(InvalidArgumentException::class, 'No class, trait or method named "fakeName" has been found.');
