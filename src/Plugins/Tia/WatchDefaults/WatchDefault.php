<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

/**
 * A set of file-watch patterns that apply when a particular framework,
 * library or project layout is detected.
 *
 * Each implementation probes for the presence of the tool it covers
 * (`applicable`) and returns glob → test-directory mappings (`defaults`)
 * that are merged into `WatchPatterns`.
 *
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
