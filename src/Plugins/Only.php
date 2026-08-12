<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\Terminable;
use Pest\Factories\Attribute;
use Pest\Factories\TestCaseMethodFactory;
use Pest\PendingCalls\TestCall;
use PHPUnit\Framework\Attributes\Group;

/**
 * @internal
 */
final class Only implements Terminable
{
    private const string TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    public static function enable(TestCall|TestCaseMethodFactory $testCall, string $group = '__pest_only'): void
    {
        if ($testCall instanceof TestCall) {
            $testCall->group($group);
        } else {
            $testCall->attributes[] = new Attribute(
                Group::class,
                [$group],
            );
        }

        if (Environment::name() === Environment::CI || Parallel::isWorker()) {
            return;
        }

        $lockFile = self::TEMPORARY_FOLDER.DIRECTORY_SEPARATOR.'only.lock';

        if (file_exists($lockFile) && $group === '__pest_only') {
            file_put_contents($lockFile, $group);

            return;
        }

        if (! file_exists($lockFile)) {
            touch($lockFile);

            file_put_contents($lockFile, $group);
        }
    }

    public static function isEnabled(): bool
    {
        $lockFile = self::TEMPORARY_FOLDER.DIRECTORY_SEPARATOR.'only.lock';

        return file_exists($lockFile);
    }

    public static function group(): string
    {
        $lockFile = self::TEMPORARY_FOLDER.DIRECTORY_SEPARATOR.'only.lock';

        if (! file_exists($lockFile)) {
            return '__pest_only';
        }

        return file_get_contents($lockFile) ?: '__pest_only'; // @phpstan-ignore-line
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(): void
    {
        if (Parallel::isWorker()) {
            return;
        }

        $lockFile = self::TEMPORARY_FOLDER.DIRECTORY_SEPARATOR.'only.lock';

        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}
