<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\Container;
use Pest\Support\JsonOutput;
use Pest\TestSuite;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\BeforeFirstTestMethodErrored;
use PHPUnit\Event\Test\Errored;
use PHPUnit\TestRunner\TestResult\Facade;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Json implements AddsOutput, HandlesArguments
{
    use Concerns\HandleArguments;

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(private readonly OutputInterface $output)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! JsonOutput::isActive() || in_array('--no-output', $arguments, true)) {
            return $arguments;
        }

        return $this->pushArgument('--no-output', $arguments);
    }

    /**
     * {@inheritDoc}
     */
    public function addOutput(int $exitCode): int
    {
        if (Parallel::isWorker()) {
            return $exitCode;
        }

        if (! $this->output instanceof JsonOutput) {
            return $exitCode;
        }

        $testSuite = Container::getInstance()->get(TestSuite::class);
        assert($testSuite instanceof TestSuite);

        $result = Facade::result();
        $failures = $this->buildFailures($result, $testSuite->rootPath);

        if ($failures === []) {
            $this->output->writeJson(json_encode(['status' => 'pass'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } else {
            $this->output->writeJson(json_encode([
                'status' => 'fail',
                'failures' => $failures,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }

        return $exitCode;
    }

    /**
     * Builds the failures array from PHPUnit's TestResult.
     *
     * @return list<array{test: string, message: string, location: string, trace: string}>
     */
    private function buildFailures(\PHPUnit\TestRunner\TestResult\TestResult $result, string $rootPath): array
    {
        $failures = [];

        foreach ($result->testErroredEvents() as $event) {
            if ($event instanceof Errored) {
                $failures[] = $this->buildFailureFromEvent($event->test(), $event->throwable(), $rootPath);
            } elseif ($event instanceof BeforeFirstTestMethodErrored) {
                $failures[] = [
                    'test' => $event->testClassName(),
                    'message' => $event->throwable()->message(),
                    'location' => $event->testClassName(),
                    'trace' => $event->throwable()->stackTrace(),
                ];
            }
        }

        foreach ($result->testFailedEvents() as $event) {
            $failures[] = $this->buildFailureFromEvent($event->test(), $event->throwable(), $rootPath);
        }

        return $failures;
    }

    /**
     * Builds a failure array from a test event.
     *
     * @return array{test: string, message: string, location: string, trace: string}
     */
    private function buildFailureFromEvent(\PHPUnit\Event\Code\Test $test, \PHPUnit\Event\Code\Throwable $throwable, string $rootPath): array
    {
        $testName = 'Unknown test';
        $location = 'unknown';

        if ($test instanceof TestMethod) {
            $testName = $test->testDox()->prettifiedMethodName();
            $file = str_replace($rootPath.DIRECTORY_SEPARATOR, '', $test->file());
            $line = $test->line();
            $location = "{$file}:{$line}";
        }

        return [
            'test' => $testName,
            'message' => $throwable->message(),
            'location' => $location,
            'trace' => str_replace($rootPath.DIRECTORY_SEPARATOR, '', $throwable->stackTrace()),
        ];
    }
}
