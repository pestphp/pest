<?php

declare(strict_types=1);

namespace Pest\Plugins;

use const DIRECTORY_SEPARATOR;

use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use Pest\Contracts\Plugins\HandlesArguments;
use PHPUnit\Event;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;

/**
 * @internal
 */
final class Profile implements HandlesArguments
{
    /** @var array<string, HRTime> */
    private static array $startTimes = [];

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        $runId = Parallel::getGlobal('PROFILE_RUN_ID');

        if (! isset($_SERVER['COLLISION_PRINTER_PROFILE']) && ! is_string($runId)) {
            return $arguments;
        }

        if (Parallel::isWorker() && is_string($runId)) {
            Event\Facade::instance()->registerSubscriber(new class implements PreparationStartedSubscriber
            {
                public function notify(PreparationStarted $event): void
                {
                    Profile::started($event);
                }
            });

            Event\Facade::instance()->registerSubscriber(new class implements FinishedSubscriber
            {
                public function notify(Finished $event): void
                {
                    Profile::finished($event);
                }
            });

            return $arguments;
        }

        if (Parallel::isEnabled()) {
            Parallel::setGlobal('PROFILE_RUN_ID', bin2hex(random_bytes(16)));
        }

        return $arguments;
    }

    public static function started(PreparationStarted $event): void
    {
        self::$startTimes[$event->test()->id()] = $event->telemetryInfo()->time();
    }

    public static function finished(Finished $event): void
    {
        $test = $event->test();

        if (! isset(self::$startTimes[$test->id()])) {
            return;
        }

        $duration = $event->telemetryInfo()->time()->duration(self::$startTimes[$test->id()]);
        $result = TestResult::fromPestParallelTestCase($test, TestResult::PASS);
        $result->setDuration($duration->asFloat() * 1000);

        unset(self::$startTimes[$test->id()]);

        $runId = Parallel::getGlobal('PROFILE_RUN_ID');

        if (is_string($runId)) {
            file_put_contents(
                self::resultPath($runId),
                base64_encode(serialize($result)).PHP_EOL,
                FILE_APPEND | LOCK_EX,
            );
        }
    }

    /** @return list<TestResult> */
    public static function results(): array
    {
        $runId = Parallel::getGlobal('PROFILE_RUN_ID');

        if (! is_string($runId)) {
            return [];
        }

        $path = self::resultPath($runId);

        if (! is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        unlink($path);

        if ($lines === false) {
            return [];
        }

        $results = [];

        foreach ($lines as $line) {
            $serializedResult = base64_decode($line, true);

            if ($serializedResult === false) {
                continue;
            }

            $result = unserialize($serializedResult, ['allowed_classes' => [TestResult::class]]);

            if ($result instanceof TestResult) {
                $results[] = $result;
            }
        }

        usort($results, fn (TestResult $a, TestResult $b): int => $b->duration <=> $a->duration);

        return array_slice($results, 0, 10);
    }

    private static function resultPath(string $runId): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'__pest_profile_'.$runId.'.txt';
    }
}
