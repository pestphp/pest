<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToBeInvokable\IsInvokable;

class InvokableClass
{
    public function __invoke(): void {}
}
