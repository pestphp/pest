<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToUseTrait\HasTrait;

use Tests\Fixtures\Arch\ToUseTrait\HasNestedTrait\NestedTrait;

trait TestTraitForInheritance
{
    use NestedTrait;

    public function testMethod()
    {
        return 'test';
    }
}