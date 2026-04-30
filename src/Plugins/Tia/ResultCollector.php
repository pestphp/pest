<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Collects per-test status + message during the run so the graph can persist
 * them for faithful replay. PHPUnit's own result cache discards messages
 * during serialisation — this collector retains them.
 *
 * @internal
 */
final class ResultCollector
{
    /**
     * @var array<string, array{status: int, message: string, time: float, assertions: int, file?: string}>
     */
    private array $results = [];

    private ?string $currentTestId = null;

    private ?string $currentTestFile = null;

    private ?float $startTime = null;

    public function testPrepared(string $testId, ?string $testFile = null): void
    {
        $this->currentTestId = $testId;
        $this->currentTestFile = $testFile;
        $this->startTime = microtime(true);
    }

    public function testPassed(): void
    {
        if ($this->currentTestId === null) {
            return;
        }

        $this->record(0, '');
    }

    public function testFailed(string $message): void
    {
        if ($this->currentTestId === null) {
            return;
        }

        $this->record(7, $message);
    }

    public function testErrored(string $message): void
    {
        if ($this->currentTestId === null) {
            return;
        }

        $this->record(8, $message);
    }

    public function testSkipped(string $message): void
    {
        if ($this->currentTestId === null) {
            return;
        }

        $this->record(1, $message);
    }

    public function testIncomplete(string $message): void
    {
        if ($this->currentTestId === null) {
            return;
        }

        $this->record(2, $message);
    }

    public function testRisky(string $message): void
    {
        if ($this->currentTestId === null) {
            return;
        }

        $this->record(5, $message);
    }

    /**
     * @return array<string, array{status: int, message: string, time: float, assertions: int, file?: string}>
     */
    public function all(): array
    {
        return $this->results;
    }

    public function recordAssertions(string $testId, int $assertions): void
    {
        if (isset($this->results[$testId])) {
            $this->results[$testId]['assertions'] = $assertions;
        }
    }

    /**
     * Injects externally-collected results (e.g. partials flushed by parallel
     * workers) into this collector so the parent can persist them in the same
     * snapshot pass as non-parallel runs.
     *
     * @param  array<string, array{status: int, message: string, time: float, assertions: int, file?: string}>  $results
     */
    public function merge(array $results): void
    {
        foreach ($results as $testId => $result) {
            $this->results[$testId] = $result;
        }
    }

    public function reset(): void
    {
        $this->results = [];
        $this->currentTestId = null;
        $this->currentTestFile = null;
        $this->startTime = null;
    }

    /**
     * Called by the Finished subscriber after a test's outcome + assertion
     * events have all fired. Clears the "currently recording" pointer so
     * the next test's events don't get mis-attributed.
     */
    public function finishTest(): void
    {
        $this->currentTestId = null;
        $this->currentTestFile = null;
        $this->startTime = null;
    }

    private function record(int $status, string $message): void
    {
        if ($this->currentTestId === null) {
            return;
        }

        $time = $this->startTime !== null
            ? round(microtime(true) - $this->startTime, 3)
            : 0.0;

        // PHPUnit can fire more than one outcome event per test — the
        // canonical case is a risky pass (`Passed` then `ConsideredRisky`).
        // Last-wins semantics preserve the most specific status; the
        // existing assertion count (if any) survives the overwrite.
        $existing = $this->results[$this->currentTestId] ?? null;

        $this->results[$this->currentTestId] = [
            'status' => $status,
            'message' => $message,
            'time' => $time,
            'assertions' => $existing['assertions'] ?? 0,
        ];

        if ($this->currentTestFile !== null) {
            $this->results[$this->currentTestId]['file'] = $this->currentTestFile;
        }
    }
}
