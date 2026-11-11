<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use PHPUnit\Util\ExcludeList;

/**
 * @internal
 */
final class BootExcludeList implements Bootstrapper
{
    /**
     * @var array<int, non-empty-string>
     */
    private const array EXCLUDE_LIST = [
        'bin',
        'overrides',
        'resources',
        'src',
        'stubs',
    ];

    public function boot(): void
    {
        $baseDirectory = dirname(__DIR__, 2);

        foreach (self::EXCLUDE_LIST as $directory) {
            ExcludeList::addDirectory($baseDirectory.DIRECTORY_SEPARATOR.$directory);
        }
    }
}
