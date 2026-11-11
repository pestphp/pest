<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use Tests\Fixtures\Covers\CoversClass3;

function testCoversFunction2(): void {}

it('guesses if the given argument is a class or function', function (): void {
    $attributes = new ReflectionClass($this)->getAttributes();

    expect($attributes[1]->getName())->toBe(CoversClass::class)
        ->and($attributes[1]->getArguments()[0])->toBe(CoversClass3::class)
        ->and($attributes[2]->getName())->toBe(CoversFunction::class)
        ->and($attributes[2]->getArguments()[0])->toBe('testCoversFunction2');
})->covers(CoversClass3::class, 'testCoversFunction2');
