<?php

declare(strict_types=1);

namespace Pest\Actions;

use Pest\Exceptions\FileOrFolderNotFound;
use Pest\TestSuite;

/**
 * @internal
 */
final class ValidatesEnvironment
{
    /**
     * The need files on the root path.
     *
     * @var array<int, string>
     */
    private const NEEDED_FILES = [
        'composer.json',
    ];

    /**
     * Validates the environment.
     */
    public static function in(TestSuite $testSuite): void
    {
        $rootPath = $testSuite->rootPath;

        $exists = function ($neededFile) use ($rootPath): bool {
            return file_exists(sprintf('%s%s%s', $rootPath, DIRECTORY_SEPARATOR, $neededFile));
        };

        foreach (self::NEEDED_FILES as $neededFile) {
            if (!$exists($neededFile)) {
                throw new FileOrFolderNotFound($neededFile);
            }
        }
    }
}
