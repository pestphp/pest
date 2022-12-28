<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use Pest\Exceptions\ShouldNotHappen;

/**
 * @internal
 */
final class BootOverrides implements Bootstrapper
{
    /**
     * The list of files to be overridden.
     *
     * @var array<int, string>
     */
    private const FILES = [
        'Runner/Filter/NameFilterIterator.php',
        'Runner/TestSuiteLoader.php',
    ];

    /**
     * Boots the Subscribers.
     */
    public function boot(): void
    {
        foreach (self::FILES as $file) {
            $file = __DIR__."/../../overrides/$file";

            if (! file_exists($file)) {
                throw ShouldNotHappen::fromMessage(sprintf('File [%s] does not exist.', $file));
            }

            require_once $file;
        }
    }
}
