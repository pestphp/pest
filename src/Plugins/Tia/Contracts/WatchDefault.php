<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Contracts;

/**
 * @internal
 */
interface WatchDefault
{
    public function applicable(): bool;

    /**
     * @return array<string, array<int, string>> pattern → list of project-relative test dirs
     */
    public function defaults(string $projectRoot, string $testPath): array;
}
