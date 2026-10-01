<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

interface ObservesExitCode
{
    public function observeExitCode(int $exitCode): void;
}
