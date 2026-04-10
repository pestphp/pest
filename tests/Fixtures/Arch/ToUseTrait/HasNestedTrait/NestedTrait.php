<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToUseTrait\HasNestedTrait;

trait NestedTrait
{
    public function nestedMethod()
    {
        return 'nested';
    }
}
