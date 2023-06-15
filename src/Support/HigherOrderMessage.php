<?php

declare(strict_types=1);

namespace Pest\Support;

use Closure;
use ReflectionClass;
use Throwable;

/**
 * @internal
 */
final class HigherOrderMessage
{
    public const UNDEFINED_METHOD = 'Method %s does not exist';

    /**
     * An optional condition that will determine if the message will be executed.
     *
     * @var (Closure(): bool)|null
     */
    public ?Closure $condition = null;

    /**
     * Creates a new higher order message.
     *
     * @param  array<int, mixed>|null  $arguments
     */
    public function __construct(
        public string $filename,
        public int $line,
        public string $name,
        public ?array $arguments
    ) {
        // ..
    }

    /**
     * Re-throws the given `$throwable` with the good line and filename.
     *
     * @template TValue of object
     *
     * @param  TValue  $target
     */
    public function call(object $target): mixed
    {
        if (is_callable($this->condition) && call_user_func(Closure::bind($this->condition, $target)) === false) {
            return $target;
        }

        if ($this->hasHigherOrderCallable()) {
            /* @phpstan-ignore-next-line */
            return (new HigherOrderCallables($target))->{$this->name}(...$this->arguments);
        }

        try {
            return is_array($this->arguments)
                ? Reflection::call($target, $this->name, $this->arguments)
                : $target->{$this->name}; /* @phpstan-ignore-line */
        } catch (Throwable $throwable) {
            Reflection::setPropertyValue($throwable, 'file', $this->filename);
            Reflection::setPropertyValue($throwable, 'line', $this->line);

            if ($throwable->getMessage() === $this->getUndefinedMethodMessage($target, $this->name)) {
                /** @var ReflectionClass<TValue> $reflection */
                $reflection = new ReflectionClass($target);
                /* @phpstan-ignore-next-line */
                $reflection = $reflection->getParentClass() ?: $reflection;
                Reflection::setPropertyValue($throwable, 'message', sprintf('Call to undefined method %s::%s()', $reflection->getName(), $this->name));
            }

            throw $throwable;
        }
    }

    /**
     * Indicates that this message should only be called when the given condition is true.
     *
     * @param  callable(): bool  $condition
     */
    public function when(callable $condition): self
    {
        $this->condition = Closure::fromCallable($condition);

        return $this;
    }

    /**
     * Determines whether or not there exists a higher order callable with the message name.
     */
    private function hasHigherOrderCallable(): bool
    {
        return in_array($this->name, get_class_methods(HigherOrderCallables::class), true);
    }

    private function getUndefinedMethodMessage(object $target, string $methodName): string
    {
        if (\PHP_MAJOR_VERSION >= 8) {
            return sprintf(self::UNDEFINED_METHOD, sprintf('%s::%s()', $target::class, $methodName));
        }

        return sprintf(self::UNDEFINED_METHOD, $methodName);
    }
}
