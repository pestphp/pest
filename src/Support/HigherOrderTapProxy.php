<?php

declare(strict_types=1);

namespace Pest\Support;

use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class HigherOrderTapProxy
{
    private const UNDEFINED_PROPERTY = 'Undefined property: P\\';

    /**
     * The target being tapped.
     *
     * @var mixed
     */
    public $target;

    /**
     * Create a new tap proxy instance.
     *
     * @param mixed $target
     */
    public function __construct($target)
    {
        $this->target = $target;
    }

    /**
     * Dynamically sets properties on the target.
     *
     * @param mixed $value
     */
    public function __set(string $property, $value): void
    {
        // @phpstan-ignore-next-line
        $this->target->{$property} = $value;
    }

    /**
     * Dynamically pass properties gets to the target.
     *
     * @return mixed
     */
    public function __get(string $property)
    {
        try {
            // @phpstan-ignore-next-line
            return $this->target->{$property};
        } catch (Throwable $throwable) {
            Reflection::setPropertyValue($throwable, 'file', Backtrace::file());
            Reflection::setPropertyValue($throwable, 'line', Backtrace::line());

            if (Str::startsWith($message = $throwable->getMessage(), self::UNDEFINED_PROPERTY)) {
                /** @var ReflectionClass $reflection */
                $reflection = (new ReflectionClass($this->target))->getParentClass();
                Reflection::setPropertyValue($throwable, 'message', sprintf('Undefined property %s::$%s', $reflection->getName(), $property));
            }

            throw $throwable;
        }
    }

    /**
     * Dynamically pass method calls to the target.
     *
     * @param array<int, mixed> $arguments
     *
     * @return mixed
     */
    public function __call(string $methodName, array $arguments)
    {
        $filename = Backtrace::file();
        $line     = Backtrace::line();

        return (new HigherOrderMessage($filename, $line, $methodName, $arguments))
            ->call($this->target);
    }
}
