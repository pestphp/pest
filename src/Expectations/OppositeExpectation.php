<?php

declare(strict_types=1);

namespace Pest\Expectations;

use Attribute;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Arch\Expectations\Targeted;
use Pest\Arch\Expectations\ToBeUsedIn;
use Pest\Arch\Expectations\ToBeUsedInNothing;
use Pest\Arch\Expectations\ToUse;
use Pest\Arch\GroupArchExpectation;
use Pest\Arch\PendingArchExpectation;
use Pest\Arch\SingleArchExpectation;
use Pest\Arch\Support\FileLineFinder;
use Pest\Exceptions\InvalidExpectation;
use Pest\Expectation;
use Pest\Support\Arr;
use Pest\Support\Exporter;
use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 *
 * @template TValue
 *
 * @method self<TValue> toBe(mixed $expected, string $message = '') Asserts that the given value is equal to the expected value.
 * @method self<TValue> toBeArray(string $message = '') Asserts that the given value is an array.
 * @method self<TValue> toBeBetween(mixed $start, mixed $end, string $message = '') Asserts that the given value is between the given start and end.
 * @method self<TValue> toBeEmpty(string $message = '') Asserts that the given value is empty.
 * @method self<TValue> toBeTrue(string $message = '') Asserts that the given value is true.
 * @method self<TValue> toBeTruthy(string $message = '') Asserts that the given value is truthy.
 * @method self<TValue> toBeFalse(string $message = '') Asserts that the given value is false.
 * @method self<TValue> toBeFalsy(string $message = '') Asserts that the given value is falsy.
 * @method self<TValue> toBeGreaterThan(mixed $expected, string $message = '') Asserts that the given value is greater than the expected value.
 * @method self<TValue> toBeGreaterThanOrEqual(mixed $expected, string $message = '') Asserts that the given value is greater than or equal to the expected value.
 * @method self<TValue> toBeLessThan(mixed $expected, string $message = '') Asserts that the given value is less than the expected value.
 * @method self<TValue> toBeLessThanOrEqual(mixed $expected, string $message = '') Asserts that the given value is less than or equal to the expected value.
 * @method self<TValue> toContain(mixed $expected, string $message = '') Asserts that the given value contains the expected value.
 * @method self<TValue> toContainEqual(mixed $expected, string $message = '') Asserts that the given value contains the expected value.
 * @method self<TValue> toContainOnlyInstancesOf(string $expected, string $message = '') Asserts that the given value contains only instances of the expected value.
 * @method self<TValue> toHaveCount(int $expected, string $message = '') Asserts that the given value has the expected count.
 * @method self<TValue> toHaveMethods(array<string> $methods, string $message = '') Asserts that the given value has the expected methods.
 * @method self<TValue> toHaveProperty(string $property, string $message = '') Asserts that the given value has the expected property.
 * @method self<TValue> toHaveProperties(array<string> $properties, string $message = '') Asserts that the given value has the expected properties.
 * @method self<TValue> toMatchArray(array<mixed> $expected, string $message = '') Asserts that the given value matches the expected array.
 * @method self<TValue> toMatchObject(object $expected, string $message = '') Asserts that the given value matches the expected object.
 * @method self<TValue> toEqual(mixed $expected, string $message = '') Asserts that the given value is equal to the expected value.
 * @method self<TValue> toEqualCanonicalizing(mixed $expected, string $message = '') Asserts that the given value is equal to the expected value.
 * @method self<TValue> toEqualWithDelta(mixed $expected, float $delta, string $message = '') Asserts that the given value is equal to the expected value.
 * @method self<TValue> toBeIn(mixed $expected, string $message = '') Asserts that the given value is in the expected value.
 * @method self<TValue> toBeInfinite(string $message = '') Asserts that the given value is infinite.
 * @method self<TValue> toBeInstanceOf(string $expected, string $message = '') Asserts that the given value is an instance of the expected value.
 * @method self<TValue> toBeBool(string $message = '') Asserts that the given value is a boolean.
 * @method self<TValue> toBeCallable(string $message = '') Asserts that the given value is callable.
 * @method self<TValue> toBeFile(string $message = '') Asserts that the given value is a file.
 * @method self<TValue> toBeFloat(string $message = '') Asserts that the given value is a float.
 * @method self<TValue> toBeInt(string $message = '') Asserts that the given value is an integer.
 * @method self<TValue> toBeIterable(string $message = '') Asserts that the given value is iterable.
 * @method self<TValue> toBeNumeric(string $message = '') Asserts that the given value is numeric.
 * @method self<TValue> toBeDigits(string $message = '') Asserts that the given value is digits.
 * @method self<TValue> toBeObject(string $message = '') Asserts that the given value is an object.
 * @method self<TValue> toBeResource(string $message = '') Asserts that the given value is a resource.
 * @method self<TValue> toBeScalar(string $message = '') Asserts that the given value is a scalar.
 * @method self<TValue> toBeString(string $message = '') Asserts that the given value is a string.
 * @method self<TValue> toBeJson(string $message = '') Asserts that the given value is a valid JSON string.
 * @method self<TValue> toBeNan(string $message = '') Asserts that the given value is NaN.
 * @method self<TValue> toBeNull(string $message = '') Asserts that the given value is null.
 * @method self<TValue> toHaveKey(mixed $key, string $message = '') Asserts that the given value has the expected key.
 * @method self<TValue> toHaveLength(int $expected, string $message = '') Asserts that the given value has the expected length.
 * @method self<TValue> toBeReadableDirectory(string $message = '') Asserts that the given value is a readable directory.
 * @method self<TValue> toBeReadableFile(string $message = '') Asserts that the given value is a readable file.
 * @method self<TValue> toBeWritableDirectory(string $message = '') Asserts that the given value is a writable directory.
 * @method self<TValue> toBeWritableFile(string $message = '') Asserts that the given value is a writable file.
 * @method self<TValue> toStartWith(mixed $expected, string $message = '') Asserts that the given value starts with the expected value.
 * @method self<TValue> toThrow(string $message = '') Asserts that the given value throws an exception.
 * @method self<TValue> toEndWith(mixed $expected, string $message = '') Asserts that the given value ends with the expected value.
 * @method self<TValue> toMatch(string $expected, string $message = '') Asserts that the given value matches the expected value.
 * @method self<TValue> toMatchConstraint(string $expected, string $message = '') Asserts that the given value matches the expected value.
 * @method self<TValue> toBeUppercase(string $message = '') Asserts that the given value is uppercase.
 * @method self<TValue> toBeLowercase(string $message = '') Asserts that the given value is lowercase.
 * @method self<TValue> toBeAlpha(string $message = '') Asserts that the given value is alphabetic.
 * @method self<TValue> toBeAlphaNumeric(string $message = '') Asserts that the given value is alphanumeric.
 * @method self<TValue> toBeSnakeCase(string $message = '') Asserts that the given value is snake case.
 * @method self<TValue> toBeKebabCase(string $message = '') Asserts that the given value is kebab case.
 * @method self<TValue> toBeCamelCase(string $message = '') Asserts that the given value is camel case.
 * @method self<TValue> toBeStudlyCase(string $message = '') Asserts that the given value is studly case.
 * @method self<TValue> toHaveSameSize(mixed $expected, string $message = '') Asserts that the given value has the same size as the expected value.
 * @method self<TValue> toHaveKebabCaseKeys(string $message = '') Asserts that the given value has kebab case keys.
 * @method self<TValue> toHaveSnakeCaseKeys(string $message = '') Asserts that the given value has snake case keys.
 * @method self<TValue> toHaveCamelCaseKeys(string $message = '') Asserts that the given value has camel case keys.
 * @method self<TValue> toHaveStudlyCaseKeys(string $message = '') Asserts that the given value has studly case keys.
 * @method self<TValue> toBeUrl(string $message = '') Asserts that the given value is a valid URL.
 * @method self<TValue> toBeUuid(string $message = '') Asserts that the given value is a valid UUID.
 *
 * @mixin Expectation<TValue>
 */
final class OppositeExpectation
{
    /**
     * Creates a new opposite expectation.
     *
     * @param  Expectation<TValue>  $original
     */
    public function __construct(private readonly Expectation $original)
    {
    }

    /**
     * Asserts that the value array not has the provided $keys.
     *
     * @param  array<int, int|string|array<int-string, mixed>>  $keys
     * @return Expectation<TValue>
     */
    public function toHaveKeys(array $keys): Expectation
    {
        foreach ($keys as $k => $key) {
            try {
                if (is_array($key)) {
                    $this->toHaveKeys(array_keys(Arr::dot($key, $k.'.')));
                } else {
                    $this->original->toHaveKey($key);
                }
            } catch (ExpectationFailedException) {
                continue;
            }

            $this->throwExpectationFailedException('toHaveKey', [$key]);
        }

        return $this->original;
    }

    /**
     * Asserts that the given expectation target does not use any of the given dependencies.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toUse(array|string $targets): ArchExpectation
    {
        return GroupArchExpectation::fromExpectations($this->original, array_map(fn (string $target): SingleArchExpectation => ToUse::make($this->original, $target)->opposite(
            fn () => $this->throwExpectationFailedException('toUse', $target),
        ), is_string($targets) ? [$targets] : $targets));
    }

    /**
     * Asserts that the given expectation target does not use the "declare(strict_types=1)" declaration.
     */
    public function toUseStrictTypes(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! str_contains((string) file_get_contents($object->path), 'declare(strict_types=1);'),
            'not to use strict types',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

    /**
     * Asserts that the given expectation target is not final.
     */
    public function toBeFinal(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! enum_exists($object->name) && ! $object->reflectionClass->isFinal(),
            'not to be final',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target is not readonly.
     */
    public function toBeReadonly(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! enum_exists($object->name) && ! $object->reflectionClass->isReadOnly() && assert(true), // @phpstan-ignore-line
            'not to be readonly',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target is not trait.
     */
    public function toBeTrait(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isTrait(),
            'not to be trait',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are not traits.
     */
    public function toBeTraits(): ArchExpectation
    {
        return $this->toBeTrait();
    }

    /**
     * Asserts that the given expectation target is not abstract.
     */
    public function toBeAbstract(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isAbstract(),
            'not to be abstract',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target does not have a specific method.
     */
    public function toHaveMethod(string $method): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->hasMethod($method),
            'to not have method',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target is not enum.
     */
    public function toBeEnum(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isEnum(),
            'not to be enum',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are not enums.
     */
    public function toBeEnums(): ArchExpectation
    {
        return $this->toBeEnum();
    }

    /**
     * Asserts that the given expectation targets is not class.
     */
    public function toBeClass(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! class_exists($object->name),
            'not to be class',
            FileLineFinder::where(fn (string $line): bool => true),
        );
    }

    /**
     * Asserts that the given expectation targets are not classes.
     */
    public function toBeClasses(): ArchExpectation
    {
        return $this->toBeClass();
    }

    /**
     * Asserts that the given expectation target is not interface.
     */
    public function toBeInterface(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isInterface(),
            'not to be interface',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are not interfaces.
     */
    public function toBeInterfaces(): ArchExpectation
    {
        return $this->toBeInterface();
    }

    /**
     * Asserts that the given expectation target to be not subclass of the given class.
     *
     * @param  class-string  $class
     */
    public function toExtend(string $class): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isSubclassOf($class),
            sprintf("not to extend '%s'", $class),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to be not have any parent class.
     */
    public function toExtendNothing(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => $object->reflectionClass->getParentClass() !== false,
            'to extend a class',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target not to implement the given interfaces.
     *
     * @param  array<int, class-string>|string  $interfaces
     */
    public function toImplement(array|string $interfaces): ArchExpectation
    {
        $interfaces = is_array($interfaces) ? $interfaces : [$interfaces];

        return Targeted::make(
            $this->original,
            function (ObjectDescription $object) use ($interfaces): bool {
                foreach ($interfaces as $interface) {
                    if ($object->reflectionClass->implementsInterface($interface)) {
                        return false;
                    }
                }

                return true;
            },
            "not to implement '".implode("', '", $interfaces)."'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to not implement any interfaces.
     */
    public function toImplementNothing(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => $object->reflectionClass->getInterfaceNames() !== [],
            'to implement an interface',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Not supported.
     *
     * @param  array<int, class-string>|string  $interfaces
     */
    public function toOnlyImplement(array|string $interfaces): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toOnlyImplement']);
    }

    /**
     * Asserts that the given expectation target to not have the given prefix.
     */
    public function toHavePrefix(string $prefix): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! str_starts_with($object->reflectionClass->getShortName(), $prefix),
            "not to have prefix '{$prefix}'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to not have the given suffix.
     */
    public function toHaveSuffix(string $suffix): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! str_ends_with($object->reflectionClass->getName(), $suffix),
            "not to have suffix '{$suffix}'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Not supported.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toOnlyUse(array|string $targets): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toOnlyUse']);
    }

    /**
     * Not supported.
     */
    public function toUseNothing(): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toUseNothing']);
    }

    /**
     * Asserts that the given expectation dependency is not used.
     */
    public function toBeUsed(): ArchExpectation
    {
        return ToBeUsedInNothing::make($this->original);
    }

    /**
     * Asserts that the given expectation dependency is not used by any of the given targets.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toBeUsedIn(array|string $targets): ArchExpectation
    {
        return GroupArchExpectation::fromExpectations($this->original, array_map(fn (string $target): GroupArchExpectation => ToBeUsedIn::make($this->original, $target)->opposite(
            fn () => $this->throwExpectationFailedException('toBeUsedIn', $target),
        ), is_string($targets) ? [$targets] : $targets));
    }

    public function toOnlyBeUsedIn(): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toOnlyBeUsedIn']);
    }

    /**
     * Asserts that the given expectation dependency is not used.
     */
    public function toBeUsedInNothing(): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toBeUsedInNothing']);
    }

    /**
     * Asserts that the given expectation dependency is not an invokable class.
     */
    public function toBeInvokable(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->hasMethod('__invoke'),
            'to not be invokable',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class'))
        );
    }

    /**
     * Asserts that the given expectation target not to have the given attribute.
     *
     * @param  class-string<Attribute>  $attribute
     */
    public function toHaveAttribute(string $attribute): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => $object->reflectionClass->getAttributes($attribute) === [],
            "to not have attribute '{$attribute}'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class'))
        );
    }

    /**
     * Handle dynamic method calls into the original expectation.
     *
     * @param  array<int, mixed>  $arguments
     * @return Expectation<TValue>|Expectation<mixed>|never
     */
    public function __call(string $name, array $arguments): Expectation
    {
        try {
            if (! is_object($this->original->value) && method_exists(PendingArchExpectation::class, $name)) {
                throw InvalidExpectation::fromMethods(['not', $name]);
            }

            /* @phpstan-ignore-next-line */
            $this->original->{$name}(...$arguments);
        } catch (ExpectationFailedException|AssertionFailedError) {
            return $this->original;
        }

        $this->throwExpectationFailedException($name, $arguments);
    }

    /**
     * Handle dynamic properties gets into the original expectation.
     *
     * @return Expectation<TValue>|Expectation<mixed>|never
     */
    public function __get(string $name): Expectation
    {
        try {
            if (! is_object($this->original->value) && method_exists(PendingArchExpectation::class, $name)) {
                throw InvalidExpectation::fromMethods(['not', $name]);
            }

            $this->original->{$name}; // @phpstan-ignore-line
        } catch (ExpectationFailedException) {
            return $this->original;
        }

        $this->throwExpectationFailedException($name);
    }

    /**
     * Creates a new expectation failed exception with a nice readable message.
     *
     * @param  array<int, mixed>|string  $arguments
     */
    public function throwExpectationFailedException(string $name, array|string $arguments = []): never
    {
        $arguments = is_array($arguments) ? $arguments : [$arguments];

        $exporter = Exporter::default();

        $toString = fn (mixed $argument): string => $exporter->shortenedExport($argument);

        throw new ExpectationFailedException(sprintf(
            'Expecting %s not %s %s.',
            $toString($this->original->value),
            strtolower((string) preg_replace('/(?<!\ )[A-Z]/', ' $0', $name)),
            implode(' ', array_map(fn (mixed $argument): string => $toString($argument), $arguments)),
        ));
    }

    /**
     * Asserts that the given expectation target does not have a constructor method.
     */
    public function toHaveConstructor(): ArchExpectation
    {
        return $this->toHaveMethod('__construct');
    }

    /**
     * Asserts that the given expectation target does not have a destructor method.
     */
    public function toHaveDestructor(): ArchExpectation
    {
        return $this->toHaveMethod('__destruct');
    }

    /**
     * Asserts that the given expectation target is not a backed enum of given type.
     */
    private function toBeBackedEnum(string $backingType): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isEnum()
                || ! (new \ReflectionEnum($object->name))->isBacked() // @phpstan-ignore-line
                || (string) (new \ReflectionEnum($object->name))->getBackingType() !== $backingType, // @phpstan-ignore-line
            'not to be '.$backingType.' backed enum',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are not string backed enums.
     */
    public function toBeStringBackedEnums(): ArchExpectation
    {
        return $this->toBeStringBackedEnum();
    }

    /**
     * Asserts that the given expectation targets are not int backed enums.
     */
    public function toBeIntBackedEnums(): ArchExpectation
    {
        return $this->toBeIntBackedEnum();
    }

    /**
     * Asserts that the given expectation target is not a string backed enum.
     */
    public function toBeStringBackedEnum(): ArchExpectation
    {
        return $this->toBeBackedEnum('string');
    }

    /**
     * Asserts that the given expectation target is not an int backed enum.
     */
    public function toBeIntBackedEnum(): ArchExpectation
    {
        return $this->toBeBackedEnum('int');
    }
}
