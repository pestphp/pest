<?php

declare(strict_types=1);

namespace Tests\Fixtures\Arch\ToUse;

use Tests\Fixtures\Arch\ToUse\Dependencies\Dependency;

final class UsesDependency
{
    public function make(): Dependency
    {
        return new Dependency;
    }
}
