<?php

declare(strict_types=1);

namespace Pest;

use BadMethodCallException;
use Pest\Concerns\Extendable;
use Pest\Concerns\RetrievesValues;
use Pest\Support\Pipeline;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 *
 * @template TValue
 *
 * @property Expectation $not  Creates the opposite expectation.
 * @property Each        $each Creates an expectation on each element on the traversable value.
 *
 * @mixin CoreExpectation
 */
final class Expectation
{
    use Extendable {
        __call as __extendsCall;
    }
    use RetrievesValues;

    /** @var CoreExpectation */
    private $coreExpectation;

    /**
     * Creates a new Expectation.
     *
     * @param TValue $value
     */
    public function __construct($value)
    {
        $this->coreExpectation = new CoreExpectation($value);
    }

    /**
     * Creates a new expectation.
     *
     * @param TValue $value
     *
     * @return Expectation<TValue>
     */
    public function and($value): Expectation
    {
        return new self($value);
    }

    /**
     * Creates a new expectation with the decoded JSON value.
     */
    public function json(): Expectation
    {
        return $this->toBeJson()->and(json_decode($this->value, true));
    }

    /**
     * Dump the expectation value and end the script.
     *
     * @param mixed $arguments
     *
     * @return never
     */
    public function dd(...$arguments): void
    {
        if (function_exists('dd')) {
            dd($this->value, ...$arguments);
        }

        var_dump($this->value);

        exit(1);
    }

    /**
     * Send the expectation value to Ray along with all given arguments.
     *
     * @param mixed $arguments
     */
    public function ray(...$arguments): self
    {
        if (function_exists('ray')) {
            // @phpstan-ignore-next-line
            ray($this->value, ...$arguments);
        }

        return $this;
    }

    /**
     * Creates the opposite expectation for the value.
     */
    public function not(): OppositeExpectation
    {
        return new OppositeExpectation($this);
    }

    /**
     * Creates an expectation on each item of the iterable "value".
     */
    public function each(callable $callback = null): Each
    {
        if (!is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        if (is_callable($callback)) {
            foreach ($this->value as $item) {
                $callback(new self($item));
            }
        }

        return new Each($this);
    }

    /**
     * Allows you to specify a sequential set of expectations for each item in a iterable "value".
     *
     * @template TSequenceValue
     *
     * @param callable(self, self): void|TSequenceValue ...$callbacks
     *
     * @noinspection PhpParamsInspection
     */
    public function sequence(...$callbacks): Expectation
    {
        if (!is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        $value          = is_array($this->value) ? $this->value : iterator_to_array($this->value);
        $keys           = array_keys($value);
        $values         = array_values($value);
        $callbacksCount = count($callbacks);

        $index = 0;

        while (count($callbacks) < count($values)) {
            $callbacks[] = $callbacks[$index];
            $index       = $index < count($values) - 1 ? $index + 1 : 0;
        }

        if ($callbacksCount > count($values)) {
            Assert::assertLessThanOrEqual(count($value), count($callbacks));
        }

        foreach ($values as $key => $item) {
            if (is_callable($callbacks[$key])) {
                call_user_func($callbacks[$key], new self($item), new self($keys[$key]));
                continue;
            }

            (new self($item))->toEqual($callbacks[$key]);
        }

        return $this;
    }

    /**
     * If the subject matches one of the given "expressions", the expression callback will run.
     *
     * @template TMatchSubject of array-key
     *
     * @param callable(): TMatchSubject|TMatchSubject                             $subject
     * @param array<TMatchSubject, (callable(Expectation<TValue>): mixed)|TValue> $expressions
     */
    public function match($subject, array $expressions): Expectation
    {
        $subject = is_callable($subject)
            ? $subject
            : function () use ($subject) {
                return $subject;
            };

        $subject = $subject();

        $matched = false;

        foreach ($expressions as $key => $callback) {
            if ($subject != $key) {
                continue;
            }

            $matched = true;

            if (is_callable($callback)) {
                $callback(new self($this->value));
                continue;
            }

            $this->and($this->value)->toEqual($callback);

            break;
        }

        if ($matched === false) {
            throw new ExpectationFailedException('Unhandled match value.');
        }

        return $this;
    }

    /**
     * Apply the callback if the given "condition" is falsy.
     *
     * @param (callable(): bool)|bool              $condition
     * @param callable(Expectation<TValue>): mixed $callback
     */
    public function unless($condition, callable $callback): Expectation
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return (bool) $condition; // @phpstan-ignore-line
            };

        return $this->when(!$condition(), $callback);
    }

    /**
     * Apply the callback if the given "condition" is truthy.
     *
     * @param (callable(): bool)|bool              $condition
     * @param callable(Expectation<TValue>): mixed $callback
     */
    public function when($condition, callable $callback): Expectation
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return (bool) $condition; // @phpstan-ignore-line
            };

        if ($condition()) {
            $callback($this->and($this->value));
        }

        return $this;
    }

    /**
     * Dynamically handle calls to the class or
     * creates a new higher order expectation.
     *
     * @param array<int, mixed> $parameters
     *
     * @return HigherOrderExpectation|Expectation
     */
    public function __call(string $method, array $parameters)
    {
        if (!$this->hasExpectation($method)) {
            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, $this->value->$method(...$parameters));
        }

        Pipeline::send(...$parameters)
            ->through($this->pipes($method, $this, Expectation::class))
            ->finally(function ($parameters) use ($method): void {
                $this->callExpectation($method, $parameters);
            });

        return $this;
    }

    /**
     * @param array<mixed> $parameters
     */
    private function callExpectation(string $name, array $parameters): void
    {
        if (method_exists($this->coreExpectation, $name)) {
            //@phpstan-ignore-next-line
            $this->coreExpectation->{$name}(...$parameters);
        } else {
            if (self::hasExtend($name)) {
                $this->__extendsCall($name, $parameters);
            }
        }
    }

    private function hasExpectation(string $name): bool
    {
        if (method_exists($this->coreExpectation, $name)) {
            return true;
        }

        if (self::hasExtend($name)) {
            return true;
        }

        return false;
    }

    /**
     * Dynamically calls methods on the class without any arguments
     * or creates a new higher order expectation.
     *
     * @return Expectation|HigherOrderExpectation
     */
    public function __get(string $name)
    {
        if ($name === 'value') {
            return $this->coreExpectation->value;
        }

        if (!method_exists($this, $name) && !method_exists($this->coreExpectation, $name) && !self::hasExtend($name)) {
            return new HigherOrderExpectation($this, $this->retrieve($name, $this->value));
        }

        /* @phpstan-ignore-next-line */
        return $this->{$name}();
    }

    public static function hasMethod(string $name): bool
    {
        return method_exists(CoreExpectation::class, $name);
    }
}
