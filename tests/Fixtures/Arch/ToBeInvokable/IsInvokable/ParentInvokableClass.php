<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToBeInvokable\IsInvokable;

class ParentInvokableClass
{
    public function __invoke(): void {}
}
