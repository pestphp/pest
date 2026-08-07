<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\ShellExitCodeCalculator;

/**
 * @internal
 */
final class Result
{
    private const int SUCCESS_EXIT = 0;

    public static function failed(Configuration $configuration, TestResult $result): bool
    {
        return ! self::ok($configuration, $result);
    }

    public static function ok(Configuration $configuration, TestResult $result): bool
    {
        return self::exitCode($configuration, $result) === self::SUCCESS_EXIT;
    }

    public static function exitCode(Configuration $configuration, TestResult $result): int
    {
        $shell = new ShellExitCodeCalculator;

        return $shell->calculate($configuration, $result);
    }
}
