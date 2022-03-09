<?php

use Pest\Factories\Attributes\Covers;
use Pest\PendingCalls\TestCall;
use Pest\TestSuite;

$runCounter = 0;

class TestCoversClass1
{
}
class TestCoversClass2
{
}

class TestCoversClass3
{
}

function testCoversFunction()
{
}

it('uses the correct PHPUnit attribute for class', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[0]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[0]->getArguments()[0])->toBe('P\Tests\Features\TestCoversClass1');
})->coversClass(TestCoversClass1::class);

it('uses the correct PHPUnit attribute for function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[1]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[1]->getArguments()[0])->toBe('foo');
})->coversFunction('foo');

it('removes duplicated attributes', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[2]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[2]->getArguments()[0])->toBe('P\Tests\Features\TestCoversClass2');
    expect($attributes[3]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[3]->getArguments()[0])->toBe('Pest\Factories\Attributes\Covers');
    expect($attributes[4]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[4]->getArguments()[0])->toBe('bar');
    expect($attributes[5]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[5]->getArguments()[0])->toBe('baz');
})
    ->coversClass(TestCoversClass2::class, TestCoversClass1::class, Covers::class)
    ->coversFunction('bar', 'foo', 'baz');

it('guesses if the given argument is a class or function', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[6]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[6]->getArguments()[0])->toBe('P\Tests\Features\TestCoversClass3');
    expect($attributes[7]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[7]->getArguments()[0])->toBe('testCoversFunction');
})->covers(TestCoversClass3::class, 'testCoversFunction');

it('appends CoversNothing to method attributes', function () {
    $phpDoc = (new ReflectionClass($this))->getMethod($this->getName());

    expect(str_contains($phpDoc->getDocComment(), '* @coversNothing'))->toBeTrue();
})->coversNothing();

it('does not append CoversNothing to other methods', function () {
    $phpDoc = (new ReflectionClass($this))->getMethod($this->getName());

    expect(str_contains($phpDoc->getDocComment(), '* @coversNothing'))->toBeFalse();
});

it('throws exception if no class nor method has been found', function () {
    $testCall = new TestCall(TestSuite::getInstance(), 'filename', 'description', fn () => 'closure');

    $testCall->covers('fakeName');
})->throws(InvalidArgumentException::class, 'No class or method named "fakeName" has been found.');
