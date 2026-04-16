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
     * @var array<string, array{status: int, message: string, time: float}>
     */
    private array $results = [];

    private ?string $currentTestId = null;

    private ?float $startTime = null;

    public function testPrepared(string $testId): void
    {
        $this->currentTestId = $testId;
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
     * @return array<string, array{status: int, message: string, time: float}>
     */
    public function all(): array
    {
        return $this->results;
    }

    public function reset(): void
    {
        $this->results = [];
        $this->currentTestId = null;
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

        $this->results[$this->currentTestId] = [
            'status' => $status,
            'message' => $message,
            'time' => $time,
        ];

        $this->currentTestId = null;
        $this->startTime = null;
    }
}
