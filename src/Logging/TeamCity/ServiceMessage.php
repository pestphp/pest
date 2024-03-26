<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity;

/**
 * @internal
 */
final class ServiceMessage
{
    private static ?int $flowId = null;

    /**
     * @param  array<string, string|int|null>  $parameters
     */
    public function __construct(
        private readonly string $type,
        private readonly array $parameters,
    ) {
    }

    public function toString(): string
    {
        $paramsToString = '';

        foreach ([...$this->parameters, 'flowId' => self::$flowId] as $key => $value) {
            $value = $this->escapeServiceMessage((string) $value);
            $paramsToString .= " $key='$value'";
        }

        return "##teamcity[$this->type$paramsToString]";
    }

    public static function testSuiteStarted(string $name, ?string $location): self
    {
        return new self('testSuiteStarted', [
            'name' => $name,
            'locationHint' => $location === null ? null : "file://$location",
        ]);
    }

    public static function testSuiteCount(int $count): self
    {
        return new self('testCount', [
            'count' => $count,
        ]);
    }

    public static function testSuiteFinished(string $name): self
    {
        return new self('testSuiteFinished', [
            'name' => $name,
        ]);
    }

    public static function testStarted(string $name, string $location): self
    {
        return new self('testStarted', [
            'name' => $name,
            'locationHint' => "pest_qn://$location",
        ]);
    }

    /**
     * @param  int  $duration  in milliseconds
     */
    public static function testFinished(string $name, int $duration): self
    {
        return new self('testFinished', [
            'name' => $name,
            'duration' => $duration,
        ]);
    }

    public static function testStdOut(string $name, string $data): self
    {
        if (! str_ends_with($data, "\n")) {
            $data .= "\n";
        }

        return new self('testStdOut', [
            'name' => $name,
            'out' => $data,
        ]);
    }

    public static function testFailed(string $name, string $message, string $details): self
    {
        return new self('testFailed', [
            'name' => $name,
            'message' => $message,
            'details' => $details,
        ]);
    }

    public static function testStdErr(string $name, string $data): self
    {
        if (! str_ends_with($data, "\n")) {
            $data .= "\n";
        }

        return new self('testStdErr', [
            'name' => $name,
            'out' => $data,
        ]);
    }

    public static function testIgnored(string $name, string $message, ?string $details = null): self
    {
        return new self('testIgnored', [
            'name' => $name,
            'message' => $message,
            'details' => $details,
        ]);
    }

    public static function comparisonFailure(string $name, string $message, string $details, string $actual, string $expected): self
    {
        return new self('testFailed', [
            'name' => $name,
            'message' => $message,
            'details' => $details,
            'type' => 'comparisonFailure',
            'actual' => $actual,
            'expected' => $expected,
        ]);
    }

    private function escapeServiceMessage(string $text): string
    {
        return str_replace(
            ['|', "'", "\n", "\r", ']', '['],
            ['||', "|'", '|n', '|r', '|]', '|['],
            $text
        );
    }

    public static function setFlowId(int $flowId): void
    {
        self::$flowId = $flowId;
    }
}
