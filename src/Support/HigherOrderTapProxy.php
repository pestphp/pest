<?php

declare(strict_types=1);

namespace Pest\Support;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
final class HigherOrderTapProxy
{
    /**
     * Create a new tap proxy instance.
     */
    public function __construct(
        public TestCase $target
    ) {
        // ..
    }

    /**
     * Dynamically sets properties on the target.
     */
    public function __set(string $property, mixed $value): void
    {
        $this->target->{$property} = $value; // @phpstan-ignore-line
    }

    /**
     * Dynamically pass properties gets to the target.
     *
     * @return mixed
     */
    public function __get(string $property)
    {
        if (property_exists($this->target, $property)) {
            return $this->target->{$property}; // @phpstan-ignore-line
        }

        $className = (new ReflectionClass($this->target))->getName();

        if (str_starts_with($className, 'P\\')) {
            $className = substr($className, 2);
        }

        trigger_error(sprintf('Undefined property %s::$%s', $className, $property), E_USER_WARNING);

        return null;
    }

    /**
     * Dynamically pass method calls to the target.
     *
     * @param  array<int, mixed>  $arguments
     * @return mixed
     */
    public function __call(string $methodName, array $arguments)
    {
        $filename = Backtrace::file();
        $line = Backtrace::line();

        return (new HigherOrderMessage($filename, $line, $methodName, $arguments))
            ->call($this->target);
    }
}
