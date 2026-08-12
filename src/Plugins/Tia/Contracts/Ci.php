<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Contracts;

/**
 * @internal
 */
interface Ci
{
    public function defaultBranch(): ?string;
}
