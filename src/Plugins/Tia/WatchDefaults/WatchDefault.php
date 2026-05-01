<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

/**
 * @internal
 */
interface WatchDefault
{
    /**
     * Whether this default set applies to the current project.
     */
    public function applicable(): bool;

    /**
     * @return array<string, array<int, string>> glob → list of project-relative test dirs
     */
    public function defaults(string $projectRoot, string $testPath): array;
}
