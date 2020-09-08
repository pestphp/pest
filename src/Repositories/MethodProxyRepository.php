<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MethodProxyRepository
{
    /**
     * @var array<string, array<string, Closure>>
     */
    private static $methodProxies = [];

    /**
     * @param class-string $fqn
     */
    public static function register(string $fqn, string $methodName, Closure $method): void
    {
        if (!array_key_exists($fqn, self::$methodProxies)) {
            self::$methodProxies[$fqn] = [];
        }

        self::$methodProxies[$fqn][$methodName] = $method;
    }

    /**
     * @param array<mixed> $params
     *
     * @return mixed
     */
    public static function evaluate(TestCase $newThis, string $methodName, array $params)
    {
        return self::$methodProxies[get_class($newThis)][$methodName]->bindTo($newThis)(...$params);
    }

    /**
     * @param class-string<TestCase> $class
     * @param array<mixed>           $params
     *
     * @return mixed
     */
    public static function staticEvaluate(string $class, string $methodName, array $params)
    {
        return self::$methodProxies[$class][$methodName]->bindTo(null, $class)(...$params);
    }
}
