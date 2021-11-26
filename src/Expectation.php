<?php

declare(strict_types=1);

namespace Pest;

use BadMethodCallException;
use Closure;
use Pest\Concerns\Extendable;
use Pest\Concerns\RetrievesValues;
use Pest\Exceptions\InvalidExpectationValue;
use Pest\Exceptions\PipeException;
use Pest\Support\ExpectationPipeline;
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
    use RetrievesValues, Extendable {
        __call as __extendsCall;
    }

    private CoreExpectation $coreExpectation;

    /**
     * Creates a new expectation.
     *
     * @param TValue $value
     */
    public function __construct(mixed $value)
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
    public function and(mixed $value): Expectation
    {
        return new self($value);
    }

    /**
     * Creates a new expectation with the decoded JSON value.
     */
    public function json(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        return $this->toBeJson()->and(json_decode($this->value, true));
    }

    /**
     * Dump the expectation value and end the script.
     *
     * @return never
     */
    public function dd(mixed ...$arguments): void
    {
        if (function_exists('dd')) {
            dd($this->value, ...$arguments);
        }

        var_dump($this->value);

        exit(1);
    }

    /**
     * Send the expectation value to Ray along with all given arguments.
     */
    public function ray(mixed ...$arguments): self
    {
        if (function_exists('ray')) {
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
     * @param (callable(self, self): void)|TSequenceValue ...$callbacks
     */
    public function sequence(mixed ...$callbacks): Expectation
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
            if ($callbacks[$key] instanceof Closure) {
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
     * @param (callable(): TMatchSubject)|TMatchSubject $subject
     * @param array<TMatchSubject, (callable(Expectation<TValue>): mixed)|TValue> $expressions
     */
    public function match(mixed $subject, array $expressions): Expectation
    {
        $subject = is_callable($subject)
            ? $subject
            : fn () => $subject;

        $subject   = $subject();

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
     * @param (callable(): bool)|bool $condition
     * @param callable(Expectation<TValue>): mixed $callback
     */
    public function unless(callable|bool $condition, callable $callback): Expectation
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return $condition;
            };

        return $this->when(!$condition(), $callback);
    }

    /**
     * Apply the callback if the given "condition" is truthy.
     *
     * @param (callable(): bool)|bool $condition
     * @param callable(Expectation<TValue>): mixed $callback
     */
    public function when(callable|bool $condition, callable $callback): Expectation
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return $condition;
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
     */
    public function __call(string $method, array $parameters): Expectation|HigherOrderExpectation
    {
        if (!$this->hasExpectation($method)) {
            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, $this->value->$method(...$parameters));
        }

        ExpectationPipeline::for($this->getExpectationClosure($method))
            ->send(...$parameters)
            ->through($this->pipes($method, $this, Expectation::class))
            ->run();
        return $this;
    }

    private function getExpectationClosure(string $name): Closure
    {
        if (method_exists($this->coreExpectation, $name)) {
            //@phpstan-ignore-next-line
            return Closure::fromCallable([$this->coreExpectation, $name]);
        }

        if (self::hasExtend($name)) {
            $extend = self::$extends[$name]->bindTo($this, Expectation::class);

            if ($extend != false) {
                return $extend;
            }
        }

        throw PipeException::expectationNotFound($name);
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
     * @return Expectation|OppositeExpectation|Each|HigherOrderExpectation|TValue
     */
    public function __get(string $name)
    {
        if ($name === 'value') {
            return $this->coreExpectation->value;
        }

        if (!method_exists($this, $name) && !method_exists($this->coreExpectation, $name) && !Expectation::hasExtend($name)) {
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
