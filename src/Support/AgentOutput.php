<?php

declare(strict_types=1);

namespace Pest\Support;

use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Test\BeforeFirstTestMethodErrored;
use PHPUnit\Event\Test\Errored;
use PHPUnit\TestRunner\TestResult\TestResult;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Node\File;

/**
 * @internal
 */
final class AgentOutput
{
    /**
     * Whether agent output mode is active.
     */
    public static function isActive(): bool
    {
        return ($_SERVER['PEST_AGENT_OUTPUT'] ?? '') === 'true';
    }

    /**
     * Builds the test results array from PHPUnit's TestResult.
     *
     * @return array{status: string, failures?: array<int, array{test: string, message: string, location: string, trace: string}>}
     */
    public static function buildTestResults(TestResult $result, string $rootPath = ''): array
    {
        $failures = [];

        foreach ($result->testErroredEvents() as $event) {
            if ($event instanceof Errored) {
                $failures[] = self::buildFailureFromEvent($event->test(), $event->throwable(), $rootPath);
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
            $failures[] = self::buildFailureFromEvent($event->test(), $event->throwable(), $rootPath);
        }

        if ($failures === []) {
            return ['status' => 'pass'];
        }

        return [
            'status' => 'fail',
            'failures' => $failures,
        ];
    }

    /**
     * Builds a failure array from a test event.
     *
     * @return array{test: string, message: string, location: string, trace: string}
     */
    private static function buildFailureFromEvent(Test $test, Throwable $throwable, string $rootPath): array
    {
        $testName = 'Unknown test';
        $location = 'unknown';

        if ($test instanceof TestMethod) {
            $testName = $test->testDox()->prettifiedMethodName();
            $file = self::toRelativePath($test->file(), $rootPath);
            $line = $test->line();
            $location = "{$file}:{$line}";
        }

        return [
            'test' => $testName,
            'message' => $throwable->message(),
            'location' => $location,
            'trace' => self::toRelativePath($throwable->stackTrace(), $rootPath),
        ];
    }

    /**
     * Builds coverage data from CodeCoverage.
     *
     * @return array{total: float, minimum?: float, files: array<int, array{path: string, coverage: float, uncovered?: array<int, string>}>}
     */
    public static function buildCoverage(CodeCoverage $codeCoverage, ?float $minimum = null): array
    {
        $report = $codeCoverage->getReport();
        $totalCoverage = $report->percentageOfExecutedLines()->asFloat();

        $files = [];

        foreach ($report->getIterator() as $file) {
            if (! $file instanceof File) {
                continue;
            }

            $fileCoverage = $file->numberOfExecutableLines() === 0
                ? 100.0
                : $file->percentageOfExecutedLines()->asFloat();

            // If minimum is set, only include files below threshold
            if ($minimum !== null && $fileCoverage >= $minimum) {
                continue;
            }

            $fileData = [
                'path' => $file->id(),
                'coverage' => round($fileCoverage, 1),
            ];

            $uncovered = Coverage::getMissingCoverage($file);
            if ($uncovered !== []) {
                $fileData['uncovered'] = $uncovered;
            }

            $files[] = $fileData;
        }

        $result = [
            'total' => round($totalCoverage, 1),
            'files' => $files,
        ];

        if ($minimum !== null) {
            $result['minimum'] = $minimum;
        }

        return $result;
    }

    /**
     * Encodes data to single-line JSON.
     *
     * @param  array<string, mixed>  $data
     */
    public static function toJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Transforms the given path to a relative path.
     */
    private static function toRelativePath(string $path, string $rootPath): string
    {
        if ($rootPath === '') {
            return $path;
        }

        return str_replace($rootPath.DIRECTORY_SEPARATOR, '', $path);
    }
}
