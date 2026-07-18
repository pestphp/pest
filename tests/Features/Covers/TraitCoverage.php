<?php

use PHPUnit\Framework\Attributes\CoversTrait as PHPUnitCoversTrait;
use Tests\Fixtures\Covers\CoversTrait;

it('uses the correct PHPUnit attribute for trait', function (): void {
    $attributes = new ReflectionClass($this)->getAttributes();

    expect($attributes[1]->getName())->toBe(PHPUnitCoversTrait::class)
        ->and($attributes[1]->getArguments()[0])->toBe(CoversTrait::class);
})->coversTrait(CoversTrait::class);
