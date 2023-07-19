<?php

declare(strict_types=1);

namespace Pest\Expectations;

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
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isFinal(),
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
            fn (ObjectDescription $object): bool => ! $object->reflectionClass->isReadOnly() && assert(true), // @phpstan-ignore-line
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
     * Asserts that the given expectation target to be subclass of the given class.
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
     * Not supported.
     */
    public function toHavePrefix(string $suffix): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toHavePrefix']);
    }

    /**
     * Not supported.
     */
    public function toHaveSuffix(string $suffix): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toHaveSuffix']);
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
}
