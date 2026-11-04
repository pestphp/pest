<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\NotToUse;

final class UsesDependency
{
    public function make(): Dependency
    {
        return new Dependency;
    }
}
