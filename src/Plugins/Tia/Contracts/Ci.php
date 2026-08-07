<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Contracts;

/**
 * @internal
 */
interface Ci
{
    /**
     * The default branch advertised by this CI, or `null` when the run
     * does not happen on it — or when it exposes no such information.
     */
    public function defaultBranch(): ?string;
}
