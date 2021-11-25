<?php

declare(strict_types=1);

namespace Pest;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use Pest\Concerns\Extendable;
use Pest\Concerns\RetrievesValues;
use Pest\Exceptions\InvalidExpectationValue;
use Pest\Support\Arr;
use Pest\Support\NullClosure;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionFunction;
use ReflectionNamedType;
use SebastianBergmann\Exporter\Exporter;
use Throwable;

/**
 * @internal
 *
 * @template TValue
 *
 * @property Expectation $not  Creates the opposite expectation.
 * @property Each        $each Creates an expectation on each element on the traversable value.
 */
final class Expectation
{
    use RetrievesValues, Extendable {
        __call as __extendsCall;
    }

    /**
     * The exporter instance, if any.
     *
     * @readonly
     */
    private ?Exporter $exporter = null;

    /**
     * Creates a new expectation.
     *
     * @param TValue $value
     */
    public function __construct(
        public mixed $value
    ) {
        // ..
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
     * Asserts that two variables have the same type and
     * value. Used on objects, it asserts that two
     * variables reference the same object.
     */
    public function toBe(mixed $expected): Expectation
    {
        Assert::assertSame($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is empty.
     */
    public function toBeEmpty(): Expectation
    {
        Assert::assertEmpty($this->value);

        return $this;
    }

    /**
     * Asserts that the value is true.
     */
    public function toBeTrue(): Expectation
    {
        Assert::assertTrue($this->value);

        return $this;
    }

    /**
     * Asserts that the value is truthy.
     */
    public function toBeTruthy(): Expectation
    {
        Assert::assertTrue((bool) $this->value);

        return $this;
    }

    /**
     * Asserts that the value is false.
     */
    public function toBeFalse(): Expectation
    {
        Assert::assertFalse($this->value);

        return $this;
    }

    /**
     * Asserts that the value is falsy.
     */
    public function toBeFalsy(): Expectation
    {
        Assert::assertFalse((bool) $this->value);

        return $this;
    }

    /**
     * Asserts that the value is greater than $expected.
     */
    public function toBeGreaterThan(int|float $expected): Expectation
    {
        Assert::assertGreaterThan($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is greater than or equal to $expected.
     */
    public function toBeGreaterThanOrEqual(int|float $expected): Expectation
    {
        Assert::assertGreaterThanOrEqual($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is less than or equal to $expected.
     */
    public function toBeLessThan(int|float $expected): Expectation
    {
        Assert::assertLessThan($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is less than $expected.
     */
    public function toBeLessThanOrEqual(int|float $expected): Expectation
    {
        Assert::assertLessThanOrEqual($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that $needle is an element of the value.
     */
    public function toContain(mixed ...$needles): Expectation
    {
        foreach ($needles as $needle) {
            if (is_string($this->value)) {
                // @phpstan-ignore-next-line
                Assert::assertStringContainsString((string) $needle, $this->value);
            } else {
                if (!is_iterable($this->value)) {
                    InvalidExpectationValue::expected('iterable');
                }
                Assert::assertContains($needle, $this->value);
            }
        }

        return $this;
    }

    /**
     * Asserts that the value starts with $expected.
     *
     * @param non-empty-string $expected
     */
    public function toStartWith(string $expected): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringStartsWith($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the value ends with $expected.
     *
     * @param non-empty-string $expected
     */
    public function toEndWith(string $expected): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringEndsWith($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that $number matches value's Length.
     */
    public function toHaveLength(int $number): Expectation
    {
        if (is_string($this->value)) {
            Assert::assertEquals($number, mb_strlen($this->value));

            return $this;
        }

        if (is_iterable($this->value)) {
            return $this->toHaveCount($number);
        }

        if (is_object($this->value)) {
            if (method_exists($this->value, 'toArray')) {
                $array = $this->value->toArray();
            } else {
                $array = (array) $this->value;
            }

            Assert::assertCount($number, $array);

            return $this;
        }

        throw new BadMethodCallException('Expectation value length is not countable.');
    }

    /**
     * Asserts that $count matches the number of elements of the value.
     */
    public function toHaveCount(int $count): Expectation
    {
        if (!is_countable($this->value) && !is_iterable($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertCount($count, $this->value);

        return $this;
    }

    /**
     * Asserts that the value contains the property $name.
     */
    public function toHaveProperty(string $name, mixed $value = null): Expectation
    {
        $this->toBeObject();

        //@phpstan-ignore-next-line
        Assert::assertTrue(property_exists($this->value, $name));

        if (func_num_args() > 1) {
            /* @phpstan-ignore-next-line */
            Assert::assertEquals($value, $this->value->{$name});
        }

        return $this;
    }

    /**
     * Asserts that the value contains the provided properties $names.
     *
     * @param iterable<array-key, string> $names
     */
    public function toHaveProperties(iterable $names): Expectation
    {
        foreach ($names as $name) {
            $this->toHaveProperty($name);
        }

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     */
    public function toEqual(mixed $expected): Expectation
    {
        Assert::assertEquals($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     * The contents of $expected and the $this->value are
     * canonicalized before they are compared. For instance, when the two
     * variables $expected and $this->value are arrays, then these arrays
     * are sorted before they are compared. When $expected and $this->value
     * are objects, each object is converted to an array containing all
     * private, protected and public attributes.
     */
    public function toEqualCanonicalizing(mixed $expected): Expectation
    {
        Assert::assertEqualsCanonicalizing($expected, $this->value);

        return $this;
    }

    /**
     * Asserts that the absolute difference between the value and $expected
     * is lower than $delta.
     */
    public function toEqualWithDelta(mixed $expected, float $delta): Expectation
    {
        Assert::assertEqualsWithDelta($expected, $this->value, $delta);

        return $this;
    }

    /**
     * Asserts that the value is one of the given values.
     *
     * @param iterable<int|string, mixed> $values
     */
    public function toBeIn(iterable $values): Expectation
    {
        Assert::assertContains($this->value, $values);

        return $this;
    }

    /**
     * Asserts that the value is infinite.
     */
    public function toBeInfinite(): Expectation
    {
        Assert::assertInfinite($this->value);

        return $this;
    }

    /**
     * Asserts that the value is an instance of $class.
     *
     * @param class-string $class
     */
    public function toBeInstanceOf(string $class): Expectation
    {
        Assert::assertInstanceOf($class, $this->value);

        return $this;
    }

    /**
     * Asserts that the value is an array.
     */
    public function toBeArray(): Expectation
    {
        Assert::assertIsArray($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type bool.
     */
    public function toBeBool(): Expectation
    {
        Assert::assertIsBool($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type callable.
     */
    public function toBeCallable(): Expectation
    {
        Assert::assertIsCallable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type float.
     */
    public function toBeFloat(): Expectation
    {
        Assert::assertIsFloat($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type int.
     */
    public function toBeInt(): Expectation
    {
        Assert::assertIsInt($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type iterable.
     */
    public function toBeIterable(): Expectation
    {
        Assert::assertIsIterable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type numeric.
     */
    public function toBeNumeric(): Expectation
    {
        Assert::assertIsNumeric($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type object.
     */
    public function toBeObject(): Expectation
    {
        Assert::assertIsObject($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type resource.
     */
    public function toBeResource(): Expectation
    {
        Assert::assertIsResource($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type scalar.
     */
    public function toBeScalar(): Expectation
    {
        Assert::assertIsScalar($this->value);

        return $this;
    }

    /**
     * Asserts that the value is of type string.
     */
    public function toBeString(): Expectation
    {
        Assert::assertIsString($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a JSON string.
     */
    public function toBeJson(): Expectation
    {
        Assert::assertIsString($this->value);

        //@phpstan-ignore-next-line
        Assert::assertJson($this->value);

        return $this;
    }

    /**
     * Asserts that the value is NAN.
     */
    public function toBeNan(): Expectation
    {
        Assert::assertNan($this->value);

        return $this;
    }

    /**
     * Asserts that the value is null.
     */
    public function toBeNull(): Expectation
    {
        Assert::assertNull($this->value);

        return $this;
    }

    /**
     * Asserts that the value array has the provided $key.
     */
    public function toHaveKey(string|int $key, mixed $value = null): Expectation
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $array = $this->value->toArray();
        } else {
            $array = (array) $this->value;
        }

        try {
            Assert::assertTrue(Arr::has($array, $key));

            /* @phpstan-ignore-next-line  */
        } catch (ExpectationFailedException $exception) {
            throw new ExpectationFailedException("Failed asserting that an array has the key '$key'", $exception->getComparisonFailure());
        }

        if (func_num_args() > 1) {
            Assert::assertEquals($value, Arr::get($array, $key));
        }

        return $this;
    }

    /**
     * Asserts that the value array has the provided $keys.
     *
     * @param array<int, int|string> $keys
     */
    public function toHaveKeys(array $keys): Expectation
    {
        foreach ($keys as $key) {
            $this->toHaveKey($key);
        }

        return $this;
    }

    /**
     * Asserts that the value is a directory.
     */
    public function toBeDirectory(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryExists($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is readable.
     */
    public function toBeReadableDirectory(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsReadable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is writable.
     */
    public function toBeWritableDirectory(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsWritable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file.
     */
    public function toBeFile(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileExists($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file and is readable.
     */
    public function toBeReadableFile(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileIsReadable($this->value);

        return $this;
    }

    /**
     * Asserts that the value is a file and is writable.
     */
    public function toBeWritableFile(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertFileIsWritable($this->value);

        return $this;
    }

    /**
     * Asserts that the value array matches the given array subset.
     *
     * @param iterable<int|string, mixed> $array
     */
    public function toMatchArray(iterable|object $array): Expectation
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $valueAsArray = $this->value->toArray();
        } else {
            $valueAsArray = (array) $this->value;
        }

        foreach ($array as $key => $value) {
            Assert::assertArrayHasKey($key, $valueAsArray);

            Assert::assertEquals(
                $value,
                $valueAsArray[$key],
                sprintf(
                    'Failed asserting that an array has a key %s with the value %s.',
                    $this->export($key),
                    $this->export($valueAsArray[$key]),
                ),
            );
        }

        return $this;
    }

    /**
     * Asserts that the value object matches a subset
     * of the properties of an given object.
     *
     * @param iterable<string, mixed>|object $object
     */
    public function toMatchObject(iterable|object $object): Expectation
    {
        foreach ((array) $object as $property => $value) {
            if (!is_object($this->value) && !is_string($this->value)) {
                InvalidExpectationValue::expected('object|string');
            }

            Assert::assertTrue(property_exists($this->value, $property));

            /* @phpstan-ignore-next-line */
            $propertyValue = $this->value->{$property};
            Assert::assertEquals(
                $value,
                $propertyValue,
                sprintf(
                    'Failed asserting that an object has a property %s with the value %s.',
                    $this->export($property),
                    $this->export($propertyValue),
                ),
            );
        }

        return $this;
    }

    /**
     * Asserts that the value matches a regular expression.
     */
    public function toMatch(string $expression): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertMatchesRegularExpression($expression, $this->value);

        return $this;
    }

    /**
     * Asserts that the value matches a constraint.
     */
    public function toMatchConstraint(Constraint $constraint): Expectation
    {
        Assert::assertThat($this->value, $constraint);

        return $this;
    }

    /**
     * Asserts that executing value throws an exception.
     *
     * @param (Closure(Throwable): mixed)|string $exception
     */
    public function toThrow(callable|string $exception, string $exceptionMessage = null): Expectation
    {
        $callback = NullClosure::create();

        if ($exception instanceof Closure) {
            $callback   = $exception;
            $parameters = (new ReflectionFunction($exception))->getParameters();

            if (1 !== count($parameters)) {
                throw new InvalidArgumentException('The given closure must have a single parameter type-hinted as the class string.');
            }

            if (!($type = $parameters[0]->getType()) instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('The given closure\'s parameter must be type-hinted as the class string.');
            }

            $exception = $type->getName();
        }

        try {
            ($this->value)();
        } catch (Throwable $e) { // @phpstan-ignore-line
            if (!class_exists($exception)) {
                Assert::assertStringContainsString($exception, $e->getMessage());

                return $this;
            }

            if ($exceptionMessage !== null) {
                Assert::assertStringContainsString($exceptionMessage, $e->getMessage());
            }

            Assert::assertInstanceOf($exception, $e);
            $callback($e);

            return $this;
        }

        if (!class_exists($exception)) {
            throw new ExpectationFailedException("Exception with message \"$exception\" not thrown.");
        }

        throw new ExpectationFailedException("Exception \"$exception\" not thrown.");
    }

    /**
     * Exports the given value.
     */
    private function export(mixed $value): string
    {
        if ($this->exporter === null) {
            $this->exporter = new Exporter();
        }

        return $this->exporter->export($value);
    }

    /**
     * Dynamically handle calls to the class or
     * creates a new higher order expectation.
     *
     * @param array<int, mixed> $parameters
     *
     * @return HigherOrderExpectation|mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (!Expectation::hasExtend($method)) {
            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, $this->value->$method(...$parameters));
        }

        return $this->__extendsCall($method, $parameters);
    }

    /**
     * Dynamically calls methods on the class without any arguments
     * or creates a new higher order expectation.
     */
    public function __get(string $name): Expectation|OppositeExpectation|Each|HigherOrderExpectation
    {
        if (!method_exists($this, $name) && !Expectation::hasExtend($name)) {
            //@phpstan-ignore-next-line
            return new HigherOrderExpectation($this, $this->retrieve($name, $this->value));
        }

        /* @phpstan-ignore-next-line */
        return $this->{$name}();
    }
}
