<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
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
