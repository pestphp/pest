<?php

declare(strict_types=1);

namespace Pest;

final class Plugin
{
    /**
     * The lazy callables to be executed
     * once the test suite boots.
     *
     * @var array<int, callable>
     *
     * @internal
     */
    public static $callables = [];

    /**
     * Lazy loads an `uses` call on the context of plugins.
     */
    public static function uses(string ...$traits): void
    {
        self::$callables[] = function () use ($traits): void {
            uses(...$traits)->in(TestSuite::getInstance()->rootPath . DIRECTORY_SEPARATOR . 'tests');
        };
    }
}
