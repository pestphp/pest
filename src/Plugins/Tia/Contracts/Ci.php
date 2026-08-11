<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Contracts;

/**
 * @internal
 */
interface Ci
{
    public function currentBranch(): ?string;

    public function defaultBranch(): ?string;
}
