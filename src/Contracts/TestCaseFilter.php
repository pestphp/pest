<?php

declare(strict_types=1);

namespace Pest\Contracts;

interface TestCaseFilter
{
    public function canLoad(string $suiteClassFile): bool;
}
