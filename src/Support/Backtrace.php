<?php

declare(strict_types=1);

namespace Pest\Support;

use Pest\Exceptions\ShouldNotHappen;

/**
 * @internal
 */
final class Backtrace
{
    private const string FILE = 'file';

    private const int BACKTRACE_OPTIONS = DEBUG_BACKTRACE_IGNORE_ARGS;

    public static function testFile(): string
    {
        return self::callSiteFile('Test file not found.');
    }

    public static function datasetsFile(): string
    {
        return self::callSiteFile('Dataset file not found.');
    }

    private static function callSiteFile(string $missingMessage): string
    {
        $fallback = null;

        foreach (debug_backtrace(self::BACKTRACE_OPTIONS) as $trace) {
            if (! isset($trace[self::FILE]) || ! is_string($trace[self::FILE])) {
                break;
            }

            $filename = $trace[self::FILE];
            $traceFile = str_replace(DIRECTORY_SEPARATOR, '/', $filename);

            if (
                Str::endsWith($traceFile, 'overrides/Runner/TestSuiteLoader.php')
                || Str::endsWith($traceFile, 'src/Bootstrappers/BootFiles.php')
            ) {
                break;
            }

            if (self::isFrameworkFile($traceFile)) {
                continue;
            }

            if (BootstrappedFiles::contains($filename)) {
                $fallback ??= $filename;

                continue;
            }

            return $filename;
        }

        if (is_string($fallback)) {
            return $fallback;
        }

        throw ShouldNotHappen::fromMessage($missingMessage);
    }

    public static function file(): string
    {
        $trace = self::backtrace();

        return $trace[self::FILE];
    }

    public static function dirname(): string
    {
        $trace = self::backtrace();

        return dirname($trace[self::FILE]);
    }

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
            if (! isset($trace['file']) || ! is_string($trace['file'])) {
                continue;
            }

            $traceFile = str_replace(DIRECTORY_SEPARATOR, '/', $trace['file']);

            if (self::isFrameworkFile($traceFile)) {
                continue;
            }

            return $trace;
        }

        throw ShouldNotHappen::fromMessage('Backtrace not found.');
    }

    private static function isFrameworkFile(string $traceFile): bool
    {
        $packageRoot = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));

        if (
            str_starts_with($traceFile, $packageRoot.'/src/')
            || str_starts_with($traceFile, $packageRoot.'/overrides/')
        ) {
            return true;
        }

        return str_contains($traceFile, '/pestphp/pest/src/')
            || str_contains($traceFile, '/pestphp/pest/overrides/');
    }
}
