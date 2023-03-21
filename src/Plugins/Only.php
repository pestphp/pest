<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\Shutdownable;
use Pest\PendingCalls\TestCall;

/**
 * @internal
 */
final class Only implements Shutdownable
{
    /**
     * The temporary folder.
     */
    private const TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    /**
     * {@inheritDoc}
     */
    public function shutdown(): void
    {
        $lockFile = self::TEMPORARY_FOLDER.DIRECTORY_SEPARATOR.'only.lock';

        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    /**
     * Creates the lock file.
     */
    public static function enable(TestCall $testCall): void
    {
        $testCall->group('__pest_only');

        $lockFile = self::TEMPORARY_FOLDER.DIRECTORY_SEPARATOR.'only.lock';

        if (! file_exists($lockFile)) {
            touch($lockFile);
        }
    }

    /**
     * Checks if "only" mode is enabled.
     */
    public static function isEnabled(): bool
    {
        $lockFile = self::TEMPORARY_FOLDER.DIRECTORY_SEPARATOR.'only.lock';

        return file_exists($lockFile);
    }
}
