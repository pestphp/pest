<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Cis;

use Pest\Plugins\Tia\Cis\Concerns\ReadsEnvironment;
use Pest\Plugins\Tia\Contracts\Ci;

/**
 * @internal
 */
final readonly class GitLab implements Ci
{
    use ReadsEnvironment;

    public function defaultBranch(): ?string
    {
        return $this->environment('CI_DEFAULT_BRANCH');
    }
}
