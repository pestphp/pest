<?php

use Pest\PendingCalls\TestCall;
use Pest\TestSuite;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\Covers\CoversClass1;
use Tests\Fixtures\Covers\CoversClass2;
use Tests\Fixtures\Covers\CoversClass3;
use Tests\Fixtures\Covers\CoversTrait;

$runCounter = 0;

function testCoversFunction() {}

it('uses the correct PHPUnit attribute for class', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[0]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[0]->getArguments()[0])->toBe('Tests\Fixtures\Covers\CoversClass1');
})->coversClass(CoversClass1::class);

it('uses the correct PHPUnit attribute for function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[1]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[1]->getArguments()[0])->toBe('testCoversFunction');
})->coversFunction('testCoversFunction');

it('removes duplicated attributes', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[2]->getName())->toBe(CoversClass::class);
    expect($attributes[2]->getArguments()[0])->toBe(CoversClass2::class);
})
    ->coversClass(CoversClass2::class, CoversClass1::class)
    ->coversFunction('testCoversFunction');

it('guesses if the given argument is a class or function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[3]->getName())->toBe(CoversClass::class);
    expect($attributes[3]->getArguments()[0])->toBe(CoversClass3::class);
})->covers(CoversClass3::class, 'testCoversFunction');

it('uses the correct PHPUnit attribute for trait', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[4]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[4]->getArguments()[0])->toBe('Tests\Fixtures\Covers\CoversTrait');
})->coversClass(CoversTrait::class);

it('appends CoversNothing to method attributes', function () {
    $phpDoc = (new ReflectionClass($this))->getMethod($this->name());

    expect(str_contains($phpDoc->getDocComment(), '* @coversNothing'))->toBeTrue();
})->coversNothing();

it('does not append CoversNothing to other methods', function () {
    $phpDoc = (new ReflectionClass($this))->getMethod($this->name());

    expect(str_contains($phpDoc->getDocComment(), '* @coversNothing'))->toBeFalse();
});

it('throws exception if no class nor method has been found', function () {
    $testCall = new TestCall(TestSuite::getInstance(), 'filename', 'description', fn () => 'closure');

    $testCall->covers('fakeName');
})->throws(InvalidArgumentException::class, 'No class or method named "fakeName" has been found.');

describe('a "describe" group of tests', function () {
    it('does not append CoversNothing to method attributes', function () {
        $phpDoc = (new ReflectionClass($this))->getMethod($this->name());

        expect(str_contains($phpDoc->getDocComment(), '* @coversNothing'))->toBeTrue();
    });
})->coversNothing();
