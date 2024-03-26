<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Exceptions\ShouldNotHappen;

/**
 * @internal
 */
final class Backtrace
{
    /**
     * @var string
     */
    private const FILE = 'file';

    private const BACKTRACE_OPTIONS = DEBUG_BACKTRACE_IGNORE_ARGS;

    /**
     * Returns the current test file.
     */
    public static function testFile(): string
    {
        $current = null;

        foreach (debug_backtrace(self::BACKTRACE_OPTIONS) as $trace) {
            assert(array_key_exists(self::FILE, $trace));

            $traceFile = str_replace(DIRECTORY_SEPARATOR, '/', $trace[self::FILE]);

            if (
                Str::endsWith($traceFile, 'overrides/Runner/TestSuiteLoader.php') ||
                Str::endsWith($traceFile, 'src/Bootstrappers/BootFiles.php')
            ) {
                break;
            }

            $current = $trace;
        }

        if ($current === null) {
            throw ShouldNotHappen::fromMessage('Test file not found.');
        }

        return $current[self::FILE];
    }

    /**
     * Returns the current datasets file.
     */
    public static function datasetsFile(): string
    {
        $current = null;

        foreach (debug_backtrace(self::BACKTRACE_OPTIONS) as $trace) {
            assert(array_key_exists(self::FILE, $trace));

            $traceFile = str_replace(DIRECTORY_SEPARATOR, '/', $trace[self::FILE]);

            if (Str::endsWith($traceFile, 'Bootstrappers/BootFiles.php') || Str::endsWith($traceFile, 'overrides/Runner/TestSuiteLoader.php')) {
                break;
            }

            $current = $trace;
        }

        if ($current === null) {
            throw ShouldNotHappen::fromMessage('Dataset file not found.');
        }

        return $current[self::FILE];
    }

    /**
     * Returns the filename that called the current function/method.
     */
    public static function file(): string
    {
        $trace = self::backtrace();

        return $trace[self::FILE];
    }

    /**
     * Returns the dirname that called the current function/method.
     */
    public static function dirname(): string
    {
        $trace = self::backtrace();

        return dirname($trace[self::FILE]);
    }

    /**
     * Returns the line that called the current function/method.
     */
    public static function line(): int
    {
        $trace = self::backtrace();

        return $trace['line'] ?? 0;
    }

    /**
     * @return array{function: string, line?: int, file: string, class?: class-string, type?: string, args?: mixed[], object?: object}
     */
    private static function backtrace(): array
    {
        $backtrace = debug_backtrace(self::BACKTRACE_OPTIONS);

        foreach ($backtrace as $trace) {
            if (! isset($trace['file'])) {
                continue;
            }

            if (($GLOBALS['__PEST_INTERNAL_TEST_SUITE'] ?? false) && str_contains($trace['file'], 'pest'.DIRECTORY_SEPARATOR.'src')) {
                continue;
            }

            if (str_contains($trace['file'], DIRECTORY_SEPARATOR.'pestphp'.DIRECTORY_SEPARATOR.'pest'.DIRECTORY_SEPARATOR.'src')) {
                continue;
            }

            return $trace;
        }

        throw ShouldNotHappen::fromMessage('Backtrace not found.');
    }
}
