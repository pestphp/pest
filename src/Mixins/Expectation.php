<?php

declare(strict_types=1);

namespace Pest\Mixins;

use BadMethodCallException;
use Closure;
use Countable;
use DateTimeInterface;
use Error;
use InvalidArgumentException;
use JsonSerializable;
use Pest\Exceptions\InvalidExpectationValue;
use Pest\Matchers\Any;
use Pest\Support\Arr;
use Pest\Support\Exporter;
use Pest\Support\NullClosure;
use Pest\Support\Str;
use Pest\TestSuite;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionNamedType;
use Throwable;
use Traversable;

/**
 * @internal
 *
 * @template TValue
 *
 * @mixin \Pest\Expectation<TValue>
 */
final class Expectation
{
    /**
     * The exporter instance, if any.
     */
    private ?Exporter $exporter = null;

    /**
     * Creates a new expectation.
     *
     * @param  TValue  $value
     */
    public function __construct(
        public mixed $value
    ) {
        // ..
    }

    /**
     * Asserts that two variables have the same type and
     * value. Used on objects, it asserts that two
     * variables reference the same object.
     *
     * @return self<TValue>
     */
    public function toBe(mixed $expected, string $message = ''): self
    {
        Assert::assertSame($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is empty.
     *
     * @return self<TValue>
     */
    public function toBeEmpty(string $message = ''): self
    {
        Assert::assertEmpty($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is true.
     *
     * @return self<TValue>
     */
    public function toBeTrue(string $message = ''): self
    {
        Assert::assertTrue($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is truthy.
     *
     * @return self<TValue>
     */
    public function toBeTruthy(string $message = ''): self
    {
        Assert::assertTrue((bool) $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is false.
     *
     * @return self<TValue>
     */
    public function toBeFalse(string $message = ''): self
    {
        Assert::assertFalse($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is falsy.
     *
     * @return self<TValue>
     */
    public function toBeFalsy(string $message = ''): self
    {
        Assert::assertFalse((bool) $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is greater than $expected.
     *
     * @return self<TValue>
     */
    public function toBeGreaterThan(int|float|string|DateTimeInterface $expected, string $message = ''): self
    {
        Assert::assertGreaterThan($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is greater than or equal to $expected.
     *
     * @return self<TValue>
     */
    public function toBeGreaterThanOrEqual(int|float|string|DateTimeInterface $expected, string $message = ''): self
    {
        Assert::assertGreaterThanOrEqual($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is less than or equal to $expected.
     *
     * @return self<TValue>
     */
    public function toBeLessThan(int|float|string|DateTimeInterface $expected, string $message = ''): self
    {
        Assert::assertLessThan($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is less than $expected.
     *
     * @return self<TValue>
     */
    public function toBeLessThanOrEqual(int|float|string|DateTimeInterface $expected, string $message = ''): self
    {
        Assert::assertLessThanOrEqual($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that $needle is an element of the value.
     *
     * @return self<TValue>
     */
    public function toContain(mixed ...$needles): self
    {
        foreach ($needles as $needle) {
            if (is_string($this->value)) {
                // @phpstan-ignore-next-line
                Assert::assertStringContainsString((string) $needle, $this->value);
            } else {
                if (! is_iterable($this->value)) {
                    InvalidExpectationValue::expected('iterable');
                }
                Assert::assertContains($needle, $this->value);
            }
        }

        return $this;
    }

    /**
     * Asserts that $needle equal an element of the value.
     *
     * @return self<TValue>
     */
    public function toContainEqual(mixed ...$needles): self
    {
        if (! is_iterable($this->value)) {
            InvalidExpectationValue::expected('iterable');
        }

        foreach ($needles as $needle) {
            Assert::assertContainsEquals($needle, $this->value);
        }

        return $this;
    }

    /**
     * Asserts that the value starts with $expected.
     *
     * @param  non-empty-string  $expected
     * @return self<TValue>
     */
    public function toStartWith(string $expected, string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringStartsWith($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value ends with $expected.
     *
     * @param  non-empty-string  $expected
     * @return self<TValue>
     */
    public function toEndWith(string $expected, string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertStringEndsWith($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that $number matches value's Length.
     *
     * @return self<TValue>
     */
    public function toHaveLength(int $number, string $message = ''): self
    {
        if (is_string($this->value)) {
            Assert::assertEquals($number, mb_strlen($this->value), $message);

            return $this;
        }

        if (is_iterable($this->value)) {
            return $this->toHaveCount($number, $message);
        }

        if (is_object($this->value)) {
            $array = method_exists($this->value, 'toArray') ? $this->value->toArray() : (array) $this->value;

            Assert::assertCount($number, $array, $message);

            return $this;
        }

        throw new BadMethodCallException('Expectation value length is not countable.');
    }

    /**
     * Asserts that $count matches the number of elements of the value.
     *
     * @return self<TValue>
     */
    public function toHaveCount(int $count, string $message = ''): self
    {
        if (! is_countable($this->value) && ! is_iterable($this->value)) {
            InvalidExpectationValue::expected('countable|iterable');
        }

        Assert::assertCount($count, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the size of the value and $expected are the same.
     *
     * @param  Countable|iterable<mixed>  $expected
     * @return self<TValue>
     */
    public function toHaveSameSize(Countable|iterable $expected, string $message = ''): self
    {
        if (! is_countable($this->value) && ! is_iterable($this->value)) {
            InvalidExpectationValue::expected('countable|iterable');
        }

        Assert::assertSameSize($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value contains the property $name.
     *
     * @return self<TValue>
     */
    public function toHaveProperty(string $name, mixed $value = new Any(), string $message = ''): self
    {
        $this->toBeObject();

        // @phpstan-ignore-next-line
        Assert::assertTrue(property_exists($this->value, $name), $message);

        if (! $value instanceof Any) {
            /* @phpstan-ignore-next-line */
            Assert::assertEquals($value, $this->value->{$name}, $message);
        }

        return $this;
    }

    /**
     * Asserts that the value contains the provided properties $names.
     *
     * @param  iterable<string, mixed>|iterable<int, string>  $names
     * @return self<TValue>
     */
    public function toHaveProperties(iterable $names, string $message = ''): self
    {
        foreach ($names as $name => $value) {
            is_int($name) ? $this->toHaveProperty($value, message: $message) : $this->toHaveProperty($name, $value, $message); // @phpstan-ignore-line
        }

        return $this;
    }

    /**
     * Asserts that the value has the method $name.
     *
     * @return self<TValue>
     */
    public function toHaveMethod(string $name, string $message = ''): self
    {
        $this->toBeObject();

        // @phpstan-ignore-next-line
        Assert::assertTrue(method_exists($this->value, $name), $message);

        return $this;
    }

    /**
     * Asserts that the value has the provided methods $names.
     *
     * @param  iterable<array-key, string>  $names
     * @return self<TValue>
     */
    public function toHaveMethods(iterable $names, string $message = ''): self
    {
        foreach ($names as $name) {
            $this->toHaveMethod($name, message: $message);
        }

        return $this;
    }

    /**
     * Asserts that two variables have the same value.
     *
     * @return self<TValue>
     */
    public function toEqual(mixed $expected, string $message = ''): self
    {
        Assert::assertEquals($expected, $this->value, $message);

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
     *
     * @return self<TValue>
     */
    public function toEqualCanonicalizing(mixed $expected, string $message = ''): self
    {
        Assert::assertEqualsCanonicalizing($expected, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the absolute difference between the value and $expected
     * is lower than $delta.
     *
     * @return self<TValue>
     */
    public function toEqualWithDelta(mixed $expected, float $delta, string $message = ''): self
    {
        Assert::assertEqualsWithDelta($expected, $this->value, $delta, $message);

        return $this;
    }

    /**
     * Asserts that the value is one of the given values.
     *
     * @param  iterable<int|string, mixed>  $values
     * @return self<TValue>
     */
    public function toBeIn(iterable $values, string $message = ''): self
    {
        Assert::assertContains($this->value, $values, $message);

        return $this;
    }

    /**
     * Asserts that the value is infinite.
     *
     * @return self<TValue>
     */
    public function toBeInfinite(string $message = ''): self
    {
        Assert::assertInfinite($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is an instance of $class.
     *
     * @param  class-string  $class
     * @return self<TValue>
     */
    public function toBeInstanceOf(string $class, string $message = ''): self
    {
        Assert::assertInstanceOf($class, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is an array.
     *
     * @return self<TValue>
     */
    public function toBeArray(string $message = ''): self
    {
        Assert::assertIsArray($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type bool.
     *
     * @return self<TValue>
     */
    public function toBeBool(string $message = ''): self
    {
        Assert::assertIsBool($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type callable.
     *
     * @return self<TValue>
     */
    public function toBeCallable(string $message = ''): self
    {
        Assert::assertIsCallable($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type float.
     *
     * @return self<TValue>
     */
    public function toBeFloat(string $message = ''): self
    {
        Assert::assertIsFloat($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type int.
     *
     * @return self<TValue>
     */
    public function toBeInt(string $message = ''): self
    {
        Assert::assertIsInt($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type iterable.
     *
     * @return self<TValue>
     */
    public function toBeIterable(string $message = ''): self
    {
        Assert::assertIsIterable($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type numeric.
     *
     * @return self<TValue>
     */
    public function toBeNumeric(string $message = ''): self
    {
        Assert::assertIsNumeric($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value contains only digits.
     *
     * @return self<TValue>
     */
    public function toBeDigits(string $message = ''): self
    {
        Assert::assertTrue(ctype_digit((string) $this->value), $message);

        return $this;
    }

    /**
     * Asserts that the value is of type object.
     *
     * @return self<TValue>
     */
    public function toBeObject(string $message = ''): self
    {
        Assert::assertIsObject($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type resource.
     *
     * @return self<TValue>
     */
    public function toBeResource(string $message = ''): self
    {
        Assert::assertIsResource($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type scalar.
     *
     * @return self<TValue>
     */
    public function toBeScalar(string $message = ''): self
    {
        Assert::assertIsScalar($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is of type string.
     *
     * @return self<TValue>
     */
    public function toBeString(string $message = ''): self
    {
        Assert::assertIsString($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is a JSON string.
     *
     * @return self<TValue>
     */
    public function toBeJson(string $message = ''): self
    {
        Assert::assertIsString($this->value, $message);

        Assert::assertJson($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is NAN.
     *
     * @return self<TValue>
     */
    public function toBeNan(string $message = ''): self
    {
        Assert::assertNan($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is null.
     *
     * @return self<TValue>
     */
    public function toBeNull(string $message = ''): self
    {
        Assert::assertNull($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value array has the provided $key.
     *
     * @return self<TValue>
     */
    public function toHaveKey(string|int $key, mixed $value = new Any(), string $message = ''): self
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $array = $this->value->toArray();
        } else {
            $array = (array) $this->value;
        }

        try {
            Assert::assertTrue(Arr::has($array, $key));

            /* @phpstan-ignore-next-line */
        } catch (ExpectationFailedException $exception) {
            if ($message === '') {
                $message = "Failed asserting that an array has the key '$key'";
            }

            throw new ExpectationFailedException($message, $exception->getComparisonFailure());
        }

        if (! $value instanceof Any) {
            Assert::assertEquals($value, Arr::get($array, $key), $message);
        }

        return $this;
    }

    /**
     * Asserts that the value array has the provided $keys.
     *
     * @param  array<int, int|string|array<array-key, mixed>>  $keys
     * @return self<TValue>
     */
    public function toHaveKeys(array $keys, string $message = ''): self
    {
        foreach ($keys as $k => $key) {
            if (is_array($key)) {
                $this->toHaveKeys(array_keys(Arr::dot($key, $k.'.')), $message);
            } else {
                $this->toHaveKey($key, message: $message);
            }
        }

        return $this;
    }

    /**
     * Asserts that the value is a directory.
     *
     * @return self<TValue>
     */
    public function toBeDirectory(string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryExists($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is readable.
     *
     * @return self<TValue>
     */
    public function toBeReadableDirectory(string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsReadable($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is a directory and is writable.
     *
     * @return self<TValue>
     */
    public function toBeWritableDirectory(string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertDirectoryIsWritable($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is a file.
     *
     * @return self<TValue>
     */
    public function toBeFile(string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileExists($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is a file and is readable.
     *
     * @return self<TValue>
     */
    public function toBeReadableFile(string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertFileIsReadable($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is a file and is writable.
     *
     * @return self<TValue>
     */
    public function toBeWritableFile(string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertFileIsWritable($this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value array matches the given array subset.
     *
     * @param  iterable<int|string, mixed>  $array
     * @return self<TValue>
     */
    public function toMatchArray(iterable $array, string $message = ''): self
    {
        if (is_object($this->value) && method_exists($this->value, 'toArray')) {
            $valueAsArray = $this->value->toArray();
        } else {
            $valueAsArray = (array) $this->value;
        }

        foreach ($array as $key => $value) {
            Assert::assertArrayHasKey($key, $valueAsArray, $message);

            if ($message === '') {
                $message = sprintf(
                    'Failed asserting that an array has a key %s with the value %s.',
                    $this->export($key),
                    $this->export($valueAsArray[$key]),
                );
            }

            Assert::assertEquals($value, $valueAsArray[$key], $message);
        }

        return $this;
    }

    /**
     * Asserts that the value object matches a subset
     * of the properties of an given object.
     *
     * @param  iterable<string, mixed>  $object
     * @return self<TValue>
     */
    public function toMatchObject(iterable $object, string $message = ''): self
    {
        foreach ((array) $object as $property => $value) {
            if (! is_object($this->value) && ! is_string($this->value)) {
                InvalidExpectationValue::expected('object|string');
            }

            Assert::assertTrue(property_exists($this->value, $property), $message);

            /* @phpstan-ignore-next-line */
            $propertyValue = $this->value->{$property};

            if ($message === '') {
                $message = sprintf(
                    'Failed asserting that an object has a property %s with the value %s.',
                    $this->export($property),
                    $this->export($propertyValue),
                );
            }

            Assert::assertEquals($value, $propertyValue, $message);
        }

        return $this;
    }

    /**
     * Asserts that the value "stringable" matches the given snapshot..
     *
     * @return self<TValue>
     */
    public function toMatchSnapshot(string $message = ''): self
    {
        $snapshots = TestSuite::getInstance()->snapshots;
        $snapshots->startNewExpectation();

        $testCase = TestSuite::getInstance()->test;
        assert($testCase instanceof TestCase);

        $string = match (true) {
            is_string($this->value) => $this->value,
            is_object($this->value) && method_exists($this->value, 'toSnapshot') => $this->value->toSnapshot(),
            is_object($this->value) && method_exists($this->value, '__toString') => $this->value->__toString(),
            is_object($this->value) && method_exists($this->value, 'toString') => $this->value->toString(),
            $this->value instanceof \Illuminate\Testing\TestResponse => $this->value->getContent(), // @phpstan-ignore-line
            is_array($this->value) => json_encode($this->value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            $this->value instanceof Traversable => json_encode(iterator_to_array($this->value), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            $this->value instanceof JsonSerializable => json_encode($this->value->jsonSerialize(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            is_object($this->value) && method_exists($this->value, 'toArray') => json_encode($this->value->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
            default => InvalidExpectationValue::expected('array|object|string'),
        };

        if ($snapshots->has()) {
            [$filename, $content] = $snapshots->get();

            Assert::assertSame(
                strtr($content, ["\r\n" => "\n", "\r" => "\n"]),
                strtr($string, ["\r\n" => "\n", "\r" => "\n"]),
                $message === '' ? "Failed asserting that the string value matches its snapshot ($filename)." : $message
            );
        } else {
            $filename = $snapshots->save($string);

            TestSuite::getInstance()->registerSnapshotChange("Snapshot created at [$filename]");
        }

        return $this;
    }

    /**
     * Asserts that the value matches a regular expression.
     *
     * @return self<TValue>
     */
    public function toMatch(string $expression, string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }
        Assert::assertMatchesRegularExpression($expression, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value matches a constraint.
     *
     * @return self<TValue>
     */
    public function toMatchConstraint(Constraint $constraint, string $message = ''): self
    {
        Assert::assertThat($this->value, $constraint, $message);

        return $this;
    }

    /**
     * @param  class-string  $class
     * @return self<TValue>
     */
    public function toContainOnlyInstancesOf(string $class, string $message = ''): self
    {
        if (! is_iterable($this->value)) {
            InvalidExpectationValue::expected('iterable');
        }

        Assert::assertContainsOnlyInstancesOf($class, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that executing value throws an exception.
     *
     * @param  (Closure(Throwable): mixed)|string  $exception
     * @return self<TValue>
     */
    public function toThrow(callable|string|Throwable $exception, ?string $exceptionMessage = null, string $message = ''): self
    {
        $callback = NullClosure::create();

        if ($exception instanceof Closure) {
            $callback = $exception;
            $parameters = (new ReflectionFunction($exception))->getParameters();

            if (count($parameters) !== 1) {
                throw new InvalidArgumentException('The given closure must have a single parameter type-hinted as the class string.');
            }

            if (! ($type = $parameters[0]->getType()) instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('The given closure\'s parameter must be type-hinted as the class string.');
            }

            $exception = $type->getName();
        }

        try {
            ($this->value)();
        } catch (Throwable $e) {

            if ($exception instanceof Throwable) {
                expect($e)
                    ->toBeInstanceOf($exception::class, $message)
                    ->and($e->getMessage())->toBe($exceptionMessage ?? $exception->getMessage(), $message);

                return $this;
            }

            if (! class_exists($exception)) {
                if ($e instanceof Error && "Class \"$exception\" not found" === $e->getMessage()) {
                    Assert::assertTrue(true);

                    throw $e;
                }

                Assert::assertStringContainsString($exception, $e->getMessage(), $message);

                return $this;
            }

            if ($exceptionMessage !== null) {
                Assert::assertStringContainsString($exceptionMessage, $e->getMessage(), $message);
            }

            Assert::assertInstanceOf($exception, $e, $message);

            $callback($e);

            return $this;
        }

        Assert::assertTrue(true);

        if (! $exception instanceof Throwable && ! class_exists($exception)) {
            throw new ExpectationFailedException("Exception with message \"$exception\" not thrown.");
        }

        throw new ExpectationFailedException("Exception \"$exception\" not thrown.");
    }

    /**
     * Exports the given value.
     */
    private function export(mixed $value): string
    {
        if (! $this->exporter instanceof \Pest\Support\Exporter) {
            $this->exporter = Exporter::default();
        }

        return $this->exporter->shortenedExport($value);
    }

    /**
     * Asserts that the value is uppercase.
     *
     * @return self<TValue>
     */
    public function toBeUppercase(string $message = ''): self
    {
        Assert::assertTrue(ctype_upper((string) $this->value), $message);

        return $this;
    }

    /**
     * Asserts that the value is lowercase.
     *
     * @return self<TValue>
     */
    public function toBeLowercase(string $message = ''): self
    {
        Assert::assertTrue(ctype_lower((string) $this->value), $message);

        return $this;
    }

    /**
     * Asserts that the value is alphanumeric.
     *
     * @return self<TValue>
     */
    public function toBeAlphaNumeric(string $message = ''): self
    {
        Assert::assertTrue(ctype_alnum((string) $this->value), $message);

        return $this;
    }

    /**
     * Asserts that the value is alpha.
     *
     * @return self<TValue>
     */
    public function toBeAlpha(string $message = ''): self
    {
        Assert::assertTrue(ctype_alpha((string) $this->value), $message);

        return $this;
    }

    /**
     * Asserts that the value is snake_case.
     *
     * @return self<TValue>
     */
    public function toBeSnakeCase(string $message = ''): self
    {
        $value = (string) $this->value;

        if ($message === '') {
            $message = "Failed asserting that {$value} is snake_case.";
        }

        Assert::assertTrue((bool) preg_match('/^[\p{Ll}_]+$/u', $value), $message);

        return $this;
    }

    /**
     * Asserts that the value is kebab-case.
     *
     * @return self<TValue>
     */
    public function toBeKebabCase(string $message = ''): self
    {
        $value = (string) $this->value;

        if ($message === '') {
            $message = "Failed asserting that {$value} is kebab-case.";
        }

        Assert::assertTrue((bool) preg_match('/^[\p{Ll}-]+$/u', $value), $message);

        return $this;
    }

    /**
     * Asserts that the value is camelCase.
     *
     * @return self<TValue>
     */
    public function toBeCamelCase(string $message = ''): self
    {
        $value = (string) $this->value;

        if ($message === '') {
            $message = "Failed asserting that {$value} is camelCase.";
        }

        Assert::assertTrue((bool) preg_match('/^\p{Ll}[\p{Ll}\p{Lu}]+$/u', $value), $message);

        return $this;
    }

    /**
     * Asserts that the value is StudlyCase.
     *
     * @return self<TValue>
     */
    public function toBeStudlyCase(string $message = ''): self
    {
        $value = (string) $this->value;

        if ($message === '') {
            $message = "Failed asserting that {$value} is StudlyCase.";
        }

        Assert::assertTrue((bool) preg_match('/^\p{Lu}+\p{Ll}[\p{Ll}\p{Lu}]+$/u', $value), $message);

        return $this;
    }

    /**
     * Asserts that the value is UUID.
     *
     * @return self<TValue>
     */
    public function toBeUuid(string $message = ''): self
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        Assert::assertTrue(Str::isUuid($this->value), $message);

        return $this;
    }

    /**
     * Asserts that the value is between 2 specified values
     *
     * @return self<TValue>
     */
    public function toBeBetween(int|float|DateTimeInterface $lowestValue, int|float|DateTimeInterface $highestValue, string $message = ''): self
    {
        Assert::assertGreaterThanOrEqual($lowestValue, $this->value, $message);
        Assert::assertLessThanOrEqual($highestValue, $this->value, $message);

        return $this;
    }

    /**
     * Asserts that the value is a url
     *
     * @return self<TValue>
     */
    public function toBeUrl(string $message = ''): self
    {
        if ($message === '') {
            $message = "Failed asserting that {$this->value} is a url.";
        }

        Assert::assertTrue(Str::isUrl((string) $this->value), $message);

        return $this;
    }
}
