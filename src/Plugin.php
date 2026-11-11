<?php

declare(strict_types=1);

namespace Pest;

final class Plugin
{
    /**
     * @var array<int, callable>
     *
     * @internal
     */
    public static array $callables = [];

    /**
     * @param  class-string  ...$traits
     */
    public static function uses(string ...$traits): void
    {
        self::$callables[] = function () use ($traits): void {
            uses(...$traits)->in(TestSuite::getInstance()->rootPath);
        };
    }
}
