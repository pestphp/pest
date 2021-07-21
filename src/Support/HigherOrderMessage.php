<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use Error;
use Pest\TestSuite;
use const PHP_MAJOR_VERSION;
use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class HigherOrderMessage
{
    public const UNDEFINED_METHOD = 'Method %s does not exist';

    /**
     * The filename where the function was originally called.
     *
     * @readonly
     *
     * @var string
     */
    public $filename;

    /**
     * The line where the function was originally called.
     *
     * @readonly
     *
     * @var int
     */
    public $line;

    /**
     * The method or property name to access.
     *
     * @readonly
     *
     * @var string
     */
    public $name;

    /**
     * The arguments.
     *
     * @var array<int, mixed>|null
     *
     * @readonly
     */
    public $arguments;

    /**
     * An optional condition that will determine if the message will be executed.
     *
     * @var callable(): bool|null
     */
    public $condition = null;

    /**
     * Creates a new higher order message.
     *
     * @param array<int, mixed>|null $arguments
     */
    public function __construct(string $filename, int $line, string $methodName, $arguments)
    {
        $this->filename   = $filename;
        $this->line       = $line;
        $this->name       = $methodName;
        $this->arguments  = $arguments;
    }

    /**
     * Attempt to call the given name as a property or method on the target.
     *
     * @return mixed
     */
    public function call(object $target)
    {
        /* @phpstan-ignore-next-line */
        if ($this->condition instanceof Closure && call_user_func(Closure::bind($this->condition, $target)) === false) {
            return $target;
        }

        if ($this->hasHigherOrderCallable()) {
            /* @phpstan-ignore-next-line */
            return (new HigherOrderCallables($target))->{$this->name}(...$this->arguments);
        }

        try {
            return is_array($this->arguments)
                ? Reflection::call($target, $this->name, $this->arguments)
                : $target->{$this->name}; // @phpstan-ignore-line
        } catch (Error $throwable) {
            if (($test = TestSuite::getInstance()->test) === null) {
                throw $this->descriptiveException($target, $throwable);
            }

            return $this->call($test);
        } catch (Throwable $throwable) {
            throw $this->descriptiveException($target, $throwable);
        }
    }

    /**
     * Update the given `$throwable` so that it points to the correct file and line number.
     */
    private function descriptiveException(object $target, Throwable $throwable): Throwable
    {
        Reflection::setPropertyValue($throwable, 'file', $this->filename);
        Reflection::setPropertyValue($throwable, 'line', $this->line);

        if ($throwable->getMessage() === self::getUndefinedMethodMessage($target, $this->name)) {
            /** @var ReflectionClass $reflection */
            $reflection = new ReflectionClass($target);
            /* @phpstan-ignore-next-line */
            $reflection = $reflection->getParentClass() ?: $reflection;
            Reflection::setPropertyValue($throwable, 'message', sprintf('Call to undefined method %s::%s()', $reflection->getName(), $this->name));
        }

        return $throwable;
    }

    /**
     * Indicates that this message should only be called when the given condition is true.
     *
     * @param callable(): bool $condition
     */
    public function when(callable $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Determines whether or not there exists a higher order callable with the message name.
     *
     * @return bool
     */
    private function hasHigherOrderCallable()
    {
        return in_array($this->name, get_class_methods(HigherOrderCallables::class), true);
    }

    private static function getUndefinedMethodMessage(object $target, string $methodName): string
    {
        if (PHP_MAJOR_VERSION >= 8) {
            return sprintf(sprintf(self::UNDEFINED_METHOD, sprintf('%s::%s()', get_class($target), $methodName)));
        }

        return sprintf(self::UNDEFINED_METHOD, $methodName);
    }
}
