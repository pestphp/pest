<?php

declare(strict_types=1);

namespace Pest\Support;

/**
 * @internal
 */
final class FlakyTestTracker
{
    /**
     * The singleton instance.
     */
    private static ?FlakyTestTracker $instance = null;

    /**
     * The tracked flaky tests.
     *
     * @var array<string, array{attempts: int, passed: bool}>
     */
    private array $flakyTests = [];

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        //
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Track a test that required retry attempts.
     */
    public function track(string $testName, int $attempts, bool $passed): void
    {
        // Only track if the test required more than 1 attempt
        if ($attempts > 1) {
            $this->flakyTests[$testName] = [
                'attempts' => $attempts,
                'passed' => $passed,
            ];
        }
    }

    /**
     * Get all tracked flaky tests.
     *
     * @return array<string, array{attempts: int, passed: bool}>
     */
    public function getFlakyTests(): array
    {
        return $this->flakyTests;
    }

    /**
     * Check if there are any tracked flaky tests.
     */
    public function hasFlakyTests(): bool
    {
        return count($this->flakyTests) > 0;
    }

    /**
     * Clear all tracked tests.
     */
    public function clear(): void
    {
        $this->flakyTests = [];
    }

    /**
     * Reset the singleton instance (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
