<?php

use Pest\Factories\Attributes\Covers;

$runCounter = 0;

class TestCoversClass1
{

}
class TestCoversClass2
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

it('uses the correct PHPUnit attribute for nothing', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes[2]->getName())->toBe('PHPUnit\Framework\Attributes\CoversNothing');
})->coversNothing();

it('removes duplicated attributes', function () {
    $attributes = (new ReflectionClass($this))->getAttributes();

    expect($attributes)->toHaveCount(7); // 3 classes, 3 functions, 1 nothing

    expect($attributes[3]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[3]->getArguments()[0])->toBe('P\Tests\Features\TestCoversClass2');
    expect($attributes[4]->getName())->toBe('PHPUnit\Framework\Attributes\CoversClass');
    expect($attributes[4]->getArguments()[0])->toBe('Pest\Factories\Attributes\Covers');
    expect($attributes[5]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[5]->getArguments()[0])->toBe('bar');
    expect($attributes[6]->getName())->toBe('PHPUnit\Framework\Attributes\CoversFunction');
    expect($attributes[6]->getArguments()[0])->toBe('baz');
})
    ->coversClass(TestCoversClass2::class, TestCoversClass1::class, Covers::class)
    ->coversNothing()
    ->coversFunction('bar', 'foo', 'baz');
