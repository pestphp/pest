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
use Pest\Support\Reflection;
use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

/**
 * @internal
 *
 * @template TValue
 *
 * @mixin Expectation<TValue>
 */
final readonly class OppositeExpectation
{
    /**
     * Creates a new opposite expectation.
     *
     * @param  Expectation<TValue>  $original
     */
    public function __construct(private Expectation $original) {}

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
     * Asserts that the given expectation target does not have the given permissions
     */
    public function toHaveFileSystemPermissions(string $permissions): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => substr(sprintf('%o', fileperms($object->path)), -4) !== $permissions,
            sprintf('permissions not to be [%s]', $permissions),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

    /**
     * Not supported.
     */
    public function toHaveLineCountLessThan(): ArchExpectation
    {
        throw InvalidExpectation::fromMethods(['not', 'toHaveLineCountLessThan']);
    }

    /**
     * Not supported.
     */
    public function toHaveMethodsDocumented(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) === false
                || array_filter(
                    Reflection::getMethodsFromReflectionClass($object->reflectionClass),
                    fn (ReflectionMethod $method): bool => (enum_exists($object->name) === false || in_array($method->name, ['from', 'tryFrom', 'cases'], true) === false)
                        && realpath($method->getFileName() ?: '/') === realpath($object->path) // @phpstan-ignore-line
                        && $method->getDocComment() !== false,
                ) === [],
            'to have methods without documentation / annotations',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class'))
        );
    }

    /**
     * Not supported.
     */
    public function toHavePropertiesDocumented(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) === false
                || array_filter(
                    Reflection::getPropertiesFromReflectionClass($object->reflectionClass),
                    fn (ReflectionProperty $property): bool => (enum_exists($object->name) === false || in_array($property->name, ['value', 'name'], true) === false)
                        && realpath($property->getDeclaringClass()->getFileName() ?: '/') === realpath($object->path) // @phpstan-ignore-line
                        && $property->isPromoted() === false
                        && $property->getDocComment() !== false,
                ) === [],
            'to have properties without documentation / annotations',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class'))
        );
    }

    /**
     * Asserts that the given expectation target does not use the "declare(strict_types=1)" declaration.
     */
    public function toUseStrictTypes(): ArchExpectation
    {
        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => ! (bool) preg_match('/^<\?php\s+declare\(.*?strict_types\s?=\s?1.*?\);/', (string) file_get_contents($object->path)),
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
     *
     * @param  array<int, string>|string  $method
     */
    public function toHaveMethod(array|string $method): ArchExpectation
    {
        $methods = is_array($method) ? $method : [$method];

        return Targeted::make(
            $this->original,
            fn (ObjectDescription $object): bool => array_filter(
                $methods,
                fn (string $method): bool => $object->reflectionClass->hasMethod($method),
            ) === [],
            'to not have methods: '.implode(', ', $methods),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target does not have the given methods.
     *
     * @param  array<int, string>  $methods
     */
    public function toHaveMethods(array $methods): ArchExpectation
    {
        return $this->toHaveMethod($methods);
    }

    /**
     * Asserts that the given expectation target not to have the public methods besides the given methods.
     *
     * @param  array<int, string>|string  $methods
     */
    public function toHavePublicMethodsBesides(array|string $methods): ArchExpectation
    {
        $methods = is_array($methods) ? $methods : [$methods];

        $state = new stdClass;

        return Targeted::make(
            $this->original,
            function (ObjectDescription $object) use ($methods, &$state): bool {
                $reflectionMethods = isset($object->reflectionClass)
                    ? Reflection::getMethodsFromReflectionClass($object->reflectionClass, ReflectionMethod::IS_PUBLIC)
                    : [];

                foreach ($reflectionMethods as $reflectionMethod) {
                    if (! in_array($reflectionMethod->name, $methods, true)) {
                        $state->contains = 'public function '.$reflectionMethod->name;

                        return false;
                    }
                }

                return true;
            },
            $methods === []
                ? 'not to have public methods'
                : sprintf("not to have public methods besides '%s'", implode("', '", $methods)),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, $state->contains)),
        );
    }

    /**
     * Asserts that the given expectation target not to have the public methods.
     */
    public function toHavePublicMethods(): ArchExpectation
    {
        return $this->toHavePublicMethodsBesides([]);
    }

    /**
     * Asserts that the given expectation target not to have the protected methods besides the given methods.
     *
     * @param  array<int, string>|string  $methods
     */
    public function toHaveProtectedMethodsBesides(array|string $methods): ArchExpectation
    {
        $methods = is_array($methods) ? $methods : [$methods];

        $state = new stdClass;

        return Targeted::make(
            $this->original,
            function (ObjectDescription $object) use ($methods, &$state): bool {
                $reflectionMethods = isset($object->reflectionClass)
                    ? Reflection::getMethodsFromReflectionClass($object->reflectionClass, ReflectionMethod::IS_PROTECTED)
                    : [];

                foreach ($reflectionMethods as $reflectionMethod) {
                    if (! in_array($reflectionMethod->name, $methods, true)) {
                        $state->contains = 'protected function '.$reflectionMethod->name;

                        return false;
                    }
                }

                return true;
            },
            $methods === []
                ? 'not to have protected methods'
                : sprintf("not to have protected methods besides '%s'", implode("', '", $methods)),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, $state->contains)),
        );
    }

    /**
     * Asserts that the given expectation target not to have the protected methods.
     */
    public function toHaveProtectedMethods(): ArchExpectation
    {
        return $this->toHaveProtectedMethodsBesides([]);
    }

    /**
     * Asserts that the given expectation target not to have the private methods besides the given methods.
     *
     * @param  array<int, string>|string  $methods
     */
    public function toHavePrivateMethodsBesides(array|string $methods): ArchExpectation
    {
        $methods = is_array($methods) ? $methods : [$methods];

        $state = new stdClass;

        return Targeted::make(
            $this->original,
            function (ObjectDescription $object) use ($methods, &$state): bool {
                $reflectionMethods = isset($object->reflectionClass)
                    ? Reflection::getMethodsFromReflectionClass($object->reflectionClass, ReflectionMethod::IS_PRIVATE)
                    : [];

                foreach ($reflectionMethods as $reflectionMethod) {
                    if (! in_array($reflectionMethod->name, $methods, true)) {
                        $state->contains = 'private function '.$reflectionMethod->name;

                        return false;
                    }
                }

                return true;
            },
            $methods === []
                ? 'not to have private methods'
                : sprintf("not to have private methods besides '%s'", implode("', '", $methods)),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, $state->contains)),
        );
    }

    /**
     * Asserts that the given expectation target not to have the private methods.
     */
    public function toHavePrivateMethods(): ArchExpectation
    {
        return $this->toHavePrivateMethodsBesides([]);
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
     * Asserts that the given expectation target not to use the given trait.
     */
    public function toUseTrait(string $trait): ArchExpectation
    {
        return $this->toUseTraits($trait);
    }

    /**
     * Asserts that the given expectation target not to use the given traits.
     *
     * @param  array<int, string>|string  $traits
     */
    public function toUseTraits(array|string $traits): ArchExpectation
    {
        $traits = is_array($traits) ? $traits : [$traits];

        return Targeted::make(
            $this->original,
            function (ObjectDescription $object) use ($traits): bool {
                foreach ($traits as $trait) {
                    if (in_array($trait, $object->reflectionClass->getTraitNames(), true)) {
                        return false;
                    }
                }

                return true;
            },
            "not to use traits '".implode("', '", $traits)."'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target not to implement the given interfaces.
     *
     * @param  array<int, string>|string  $interfaces
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
     */
    public function toOnlyImplement(): void
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
     */
    public function toOnlyUse(): void
    {
        throw InvalidExpectation::fromMethods(['not', 'toOnlyUse']);
    }

    /**
     * Not supported.
     */
    public function toUseNothing(): void
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

    public function toOnlyBeUsedIn(): void
    {
        throw InvalidExpectation::fromMethods(['not', 'toOnlyBeUsedIn']);
    }

    /**
     * Asserts that the given expectation dependency is not used.
     */
    public function toBeUsedInNothing(): void
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
