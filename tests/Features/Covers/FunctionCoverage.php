<?php

use PHPUnit\Framework\Attributes\CoversFunction;

function testCoversFunction(): void {}

it('uses the correct PHPUnit attribute for function', function (): void {
    $attributes = new ReflectionClass($this)->getAttributes();

    expect($attributes[1]->getName())->toBe(CoversFunction::class)
        ->and($attributes[1]->getArguments()[0])->toBe('testCoversFunction');
})->coversFunction('testCoversFunction');
