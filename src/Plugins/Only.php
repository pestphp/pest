<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Contracts\Plugins\Terminable;
use Pest\Factories\Attribute;
use Pest\Factories\TestCaseMethodFactory;
use Pest\PendingCalls\TestCall;
use PHPUnit\Framework\Attributes\Group;

/**
 * @internal
 */
final class Only implements HandlesArguments, Terminable
{
    private const string DEFAULT_GROUP = '__pest_only';

    private const string WORKER_GROUP = 'ONLY_GROUP';

    private static ?string $group = null;

    public function handleArguments(array $arguments): array
    {
        if (! Parallel::isWorker()) {
            self::shareGroupWithWorkers();
        }

        return $arguments;
    }

    public static function enable(TestCall|TestCaseMethodFactory $testCall, string $group = self::DEFAULT_GROUP): void
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

        if (self::$group === null || $group === self::DEFAULT_GROUP) {
            self::$group = $group;
            self::shareGroupWithWorkers();
        }
    }

    public static function isEnabled(): bool
    {
        return self::selectedGroup() !== null;
    }

    public static function group(): string
    {
        return self::selectedGroup() ?: self::DEFAULT_GROUP;
    }

    public function terminate(): void
    {
        self::$group = null;
        self::shareGroupWithWorkers();
    }

    private static function selectedGroup(): ?string
    {
        if (! Parallel::isWorker()) {
            return self::$group;
        }

        $group = Parallel::getGlobal(self::WORKER_GROUP);

        return is_string($group) ? base64_decode($group) : null;
    }

    private static function shareGroupWithWorkers(): void
    {
        if (Parallel::isEnabled()) {
            Parallel::setGlobal(self::WORKER_GROUP, self::$group === null ? false : base64_encode(self::$group));
        }
    }
}
