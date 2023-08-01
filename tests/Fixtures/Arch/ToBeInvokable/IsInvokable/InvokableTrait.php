<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToBeInvokable\IsInvokable;

trait InvokableTrait
{
    public function __invoke(): void
    {
        //
    }
}
