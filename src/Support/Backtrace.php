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
            if (Str::endsWith($trace[self::FILE], (string) realpath('vendor/phpunit/phpunit/src/Util/FileLoader.php'))) {
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
     * Returns the filename that called the current function/method.
     */
    public static function file(): string
    {
        $trace = debug_backtrace(self::BACKTRACE_OPTIONS)[1];

        assert(array_key_exists(self::FILE, $trace));

        return $trace[self::FILE];
    }

    /**
     * Returns the dirname that called the current function/method.
     */
    public static function dirname(): string
    {
        $trace = debug_backtrace(self::BACKTRACE_OPTIONS)[1];

        assert(array_key_exists(self::FILE, $trace));

        return dirname($trace[self::FILE]);
    }

    /**
     * Returns the line that called the current function/method.
     */
    public static function line(): int
    {
        $trace = debug_backtrace(self::BACKTRACE_OPTIONS)[1];

        assert(array_key_exists('line', $trace));

        return $trace['line'];
    }
}
