<?php

declare(strict_types=1);

namespace Pest;

use Attribute;
use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use OutOfRangeException;
use Pest\Arch\Contracts\ArchExpectation;
use Pest\Arch\Expectations\Targeted;
use Pest\Arch\Expectations\ToBeUsedIn;
use Pest\Arch\Expectations\ToBeUsedInNothing;
use Pest\Arch\Expectations\ToOnlyBeUsedIn;
use Pest\Arch\Expectations\ToOnlyUse;
use Pest\Arch\Expectations\ToUse;
use Pest\Arch\Expectations\ToUseNothing;
use Pest\Arch\PendingArchExpectation;
use Pest\Arch\Support\FileLineFinder;
use Pest\Concerns\Extendable;
use Pest\Concerns\Pipeable;
use Pest\Concerns\Retrievable;
use Pest\Exceptions\ExpectationNotFound;
use Pest\Exceptions\InvalidExpectation;
use Pest\Exceptions\InvalidExpectationValue;
use Pest\Expectations\EachExpectation;
use Pest\Expectations\HigherOrderExpectation;
use Pest\Expectations\OppositeExpectation;
use Pest\Matchers\Any;
use Pest\Support\ExpectationPipeline;
use Pest\Support\Reflection;
use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @template TValue
 *
 * @property OppositeExpectation $not Creates the opposite expectation.
 * @property EachExpectation $each Creates an expectation on each element on the traversable value.
 * @property PendingArchExpectation $classes
 * @property PendingArchExpectation $traits
 * @property PendingArchExpectation $interfaces
 * @property PendingArchExpectation $enums
 *
 * @mixin Mixins\Expectation<TValue>
 * @mixin PendingArchExpectation
 */
final class Expectation
{
    /** @use Extendable<self<TValue>> */
    use Extendable;

    use Pipeable;
    use Retrievable;

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
     * Creates a new expectation.
     *
     * @template TAndValue
     *
     * @param  TAndValue  $value
     * @return self<TAndValue>
     */
    public function and(mixed $value): Expectation
    {
        return $value instanceof self ? $value : new self($value);
    }

    /**
     * Creates a new expectation with the decoded JSON value.
     *
     * @return self<array<int|string, mixed>|bool>
     */
    public function json(): Expectation
    {
        if (! is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        $this->toBeJson();

        /** @var array<int|string, mixed>|bool $value */
        $value = json_decode($this->value, true, 512, JSON_THROW_ON_ERROR);

        return $this->and($value);
    }

    /**
     * Dump the expectation value.
     *
     * @return self<TValue>
     */
    public function dump(mixed ...$arguments): self
    {
        if (function_exists('dump')) {
            dump($this->value, ...$arguments);
        } else {
            var_dump($this->value);
        }

        return $this;
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
     * Dump the expectation value when the result of the condition is truthy.
     *
     * @param  (\Closure(TValue): bool)|bool  $condition
     * @return self<TValue>
     */
    public function ddWhen(Closure|bool $condition, mixed ...$arguments): Expectation
    {
        $condition = $condition instanceof Closure ? $condition($this->value) : $condition;

        if (! $condition) {
            return $this;
        }

        $this->dd(...$arguments);
    }

    /**
     * Dump the expectation value when the result of the condition is falsy.
     *
     * @param  (\Closure(TValue): bool)|bool  $condition
     * @return self<TValue>
     */
    public function ddUnless(Closure|bool $condition, mixed ...$arguments): Expectation
    {
        $condition = $condition instanceof Closure ? $condition($this->value) : $condition;

        if ($condition) {
            return $this;
        }

        $this->dd(...$arguments);
    }

    /**
     * Send the expectation value to Ray along with all given arguments.
     *
     * @return self<TValue>
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
     *
     * @return OppositeExpectation<TValue>
     */
    public function not(): OppositeExpectation
    {
        return new OppositeExpectation($this);
    }

    /**
     * Creates an expectation on each item of the iterable "value".
     *
     * @return EachExpectation<TValue>
     */
    public function each(?callable $callback = null): EachExpectation
    {
        if (! is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        if (is_callable($callback)) {
            foreach ($this->value as $key => $item) {
                $callback(new self($item), $key);
            }
        }

        return new EachExpectation($this);
    }

    /**
     * Allows you to specify a sequential set of expectations for each item in a iterable "value".
     *
     * @template TSequenceValue
     *
     * @param  (callable(self<TValue>, self<string|int>): void)|TSequenceValue  ...$callbacks
     * @return self<TValue>
     */
    public function sequence(mixed ...$callbacks): self
    {
        if (! is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        if ($callbacks === []) {
            throw new InvalidArgumentException('No sequence expectations defined.');
        }

        $index = $valuesCount = 0;

        foreach ($this->value as $key => $value) {
            $valuesCount++;

            if ($callbacks[$index] instanceof Closure) {
                $callbacks[$index](new self($value), new self($key));
            } else {
                (new self($value))->toEqual($callbacks[$index]);
            }

            $index = isset($callbacks[$index + 1]) ? $index + 1 : 0;
        }

        if ($valuesCount < count($callbacks)) {
            throw new OutOfRangeException('Sequence expectations are more than the iterable items.');
        }

        return $this;
    }

    /**
     * If the subject matches one of the given "expressions", the expression callback will run.
     *
     * @template TMatchSubject of array-key
     *
     * @param  (callable(): TMatchSubject)|TMatchSubject  $subject
     * @param  array<TMatchSubject, (callable(self<TValue>): mixed)|TValue>  $expressions
     * @return self<TValue>
     */
    public function match(mixed $subject, array $expressions): self
    {
        $subject = $subject instanceof Closure ? $subject() : $subject;

        $matched = false;

        foreach ($expressions as $key => $callback) {
            if ($subject != $key) { // @pest-arch-ignore-line
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
     * @param  (callable(): bool)|bool  $condition
     * @param  callable(Expectation<TValue>): mixed  $callback
     * @return self<TValue>
     */
    public function unless(callable|bool $condition, callable $callback): Expectation
    {
        $condition = is_callable($condition)
            ? $condition
            : static fn (): bool => $condition;

        return $this->when(! $condition(), $callback);
    }

    /**
     * Apply the callback if the given "condition" is truthy.
     *
     * @param  (callable(): bool)|bool  $condition
     * @param  callable(self<TValue>): mixed  $callback
     * @return self<TValue>
     */
    public function when(callable|bool $condition, callable $callback): self
    {
        $condition = is_callable($condition)
            ? $condition
            : static fn (): bool => $condition;

        if ($condition()) {
            $callback($this->and($this->value));
        }

        return $this;
    }

    /**
     * Dynamically calls methods on the class or creates a new higher order expectation.
     *
     * @param  array<int, mixed>  $parameters
     * @return Expectation<TValue>|HigherOrderExpectation<Expectation<TValue>, TValue>
     */
    public function __call(string $method, array $parameters): Expectation|HigherOrderExpectation|PendingArchExpectation|ArchExpectation
    {
        if (! self::hasMethod($method)) {
            if (! is_object($this->value) && method_exists(PendingArchExpectation::class, $method)) {
                $pendingArchExpectation = new PendingArchExpectation($this, []);

                return $pendingArchExpectation->$method(...$parameters); // @phpstan-ignore-line
            }

            if (! is_object($this->value)) {
                throw new BadMethodCallException(sprintf(
                    'Method "%s" does not exist in %s.',
                    $method,
                    gettype($this->value)
                ));
            }

            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, call_user_func_array($this->value->$method(...), $parameters));
        }

        $closure = $this->getExpectationClosure($method);
        $reflectionClosure = new \ReflectionFunction($closure);
        $expectation = $reflectionClosure->getClosureThis();

        if ($reflectionClosure->getReturnType()?->__toString() === ArchExpectation::class) {
            return $closure(...$parameters);
        }

        assert(is_object($expectation));

        ExpectationPipeline::for($closure)
            ->send(...$parameters)
            ->through($this->pipes($method, $expectation, Expectation::class))
            ->run();

        return $this;
    }

    /**
     * Creates a new expectation closure from the given name.
     *
     * @throws ExpectationNotFound
     */
    private function getExpectationClosure(string $name): Closure
    {
        if (method_exists(Mixins\Expectation::class, $name)) {
            // @phpstan-ignore-next-line
            return Closure::fromCallable([new Mixins\Expectation($this->value), $name]);
        }

        if (self::hasExtend($name)) {
            $extend = self::$extends[$name]->bindTo($this, Expectation::class);

            if ($extend != false) { // @pest-arch-ignore-line
                return $extend;
            }
        }

        throw ExpectationNotFound::fromName($name);
    }

    /**
     * Dynamically calls methods on the class without any arguments or creates a new higher order expectation.
     *
     * @return Expectation<TValue>|OppositeExpectation<TValue>|EachExpectation<TValue>|HigherOrderExpectation<Expectation<TValue>, TValue|null>|TValue
     */
    public function __get(string $name): mixed
    {
        if (! self::hasMethod($name)) {
            if (! is_object($this->value) && method_exists(PendingArchExpectation::class, $name)) {
                /* @phpstan-ignore-next-line */
                return $this->{$name}();
            }

            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, $this->retrieve($name, $this->value));
        }

        /* @phpstan-ignore-next-line */
        return $this->{$name}();
    }

    /**
     * Checks if the given expectation method exists.
     */
    public static function hasMethod(string $name): bool
    {
        return method_exists(self::class, $name)
            || method_exists(Mixins\Expectation::class, $name)
            || self::hasExtend($name);
    }

    /**
     * Matches any value.
     */
    public function any(): Any
    {
        return new Any;
    }

    /**
     * Asserts that the given expectation target use the given dependencies.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toUse(array|string $targets): ArchExpectation
    {
        return ToUse::make($this, $targets);
    }

    /**
     * Asserts that the given expectation target does have the given permissions
     */
    public function toHaveFileSystemPermissions(string $permissions): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => substr(sprintf('%o', fileperms($object->path)), -4) === $permissions,
            sprintf('permissions to be [%s]', $permissions),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

    /**
     * Asserts that the given expectation target to have line count less than the given number.
     */
    public function toHaveLineCountLessThan(int $lines): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => count(file($object->path)) < $lines, // @phpstan-ignore-line
            sprintf('to have less than %d lines of code', $lines),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

    /**
     * Asserts that the given expectation target have all methods documented.
     */
    public function toHaveMethodsDocumented(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) === false
                || array_filter(
                    Reflection::getMethodsFromReflectionClass($object->reflectionClass),
                    fn (ReflectionMethod $method): bool => (enum_exists($object->name) === false || in_array($method->name, ['from', 'tryFrom', 'cases'], true) === false)
                        && realpath($method->getFileName() ?: '/') === realpath($object->path) // @phpstan-ignore-line
                        && $method->getDocComment() === false,
                ) === [],
            'to have methods with documentation / annotations',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class'))
        );
    }

    /**
     * Asserts that the given expectation target have all properties documented.
     */
    public function toHavePropertiesDocumented(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) === false
                || array_filter(
                    Reflection::getPropertiesFromReflectionClass($object->reflectionClass),
                    fn (ReflectionProperty $property): bool => (enum_exists($object->name) === false || in_array($property->name, ['value', 'name'], true) === false)
                        && realpath($property->getDeclaringClass()->getFileName() ?: '/') === realpath($object->path) // @phpstan-ignore-line
                        && $property->isPromoted() === false
                        && $property->getDocComment() === false,
                ) === [],
            'to have properties with documentation / annotations',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class'))
        );
    }

    /**
     * Asserts that the given expectation target use the "declare(strict_types=1)" declaration.
     */
    public function toUseStrictTypes(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => (bool) preg_match('/^<\?php\s*(\/\*[\s\S]*?\*\/|\/\/[^\r\n]*(?:\r?\n|$)|\s)*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m', (string) file_get_contents($object->path)),
            'to use strict types',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

    /**
     * Asserts that the given expectation target uses strict equality.
     */
    public function toUseStrictEquality(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => ! str_contains((string) file_get_contents($object->path), ' == ') && ! str_contains((string) file_get_contents($object->path), ' != '),
            'to use strict equality',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, ' == ') || str_contains($line, ' != ')),
        );
    }

    /**
     * Asserts that the given expectation target is final.
     */
    public function toBeFinal(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => ! enum_exists($object->name) && isset($object->reflectionClass) && $object->reflectionClass->isFinal(),
            'to be final',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target is readonly.
     */
    public function toBeReadonly(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => ! enum_exists($object->name) && isset($object->reflectionClass) && $object->reflectionClass->isReadOnly() && assert(true), // @phpstan-ignore-line
            'to be readonly',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target is trait.
     */
    public function toBeTrait(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->isTrait(),
            'to be trait',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are traits.
     */
    public function toBeTraits(): ArchExpectation
    {
        return $this->toBeTrait();
    }

    /**
     * Asserts that the given expectation target is abstract.
     */
    public function toBeAbstract(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->isAbstract(),
            'to be abstract',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target has a specific method.
     *
     * @param  array<int, string>|string  $method
     */
    public function toHaveMethod(array|string $method): ArchExpectation
    {
        $methods = is_array($method) ? $method : [$method];

        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => count(array_filter($methods, fn (string $method): bool => isset($object->reflectionClass) && $object->reflectionClass->hasMethod($method))) === count($methods),
            sprintf("to have method '%s'", implode("', '", $methods)),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target has a specific methods.
     *
     * @param  array<int, string>  $methods
     */
    public function toHaveMethods(array $methods): ArchExpectation
    {
        return $this->toHaveMethod($methods);
    }

    /**
     * Not supported.
     */
    public function toHavePublicMethodsBesides(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePublicMethodsBesides']);
    }

    /**
     * Not supported.
     */
    public function toHavePublicMethods(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePublicMethods']);
    }

    /**
     * Not supported.
     */
    public function toHaveProtectedMethodsBesides(): void
    {
        throw InvalidExpectation::fromMethods(['toHaveProtectedMethodsBesides']);
    }

    /**
     * Not supported.
     */
    public function toHaveProtectedMethods(): void
    {
        throw InvalidExpectation::fromMethods(['toHaveProtectedMethods']);
    }

    /**
     * Not supported.
     */
    public function toHavePrivateMethodsBesides(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePrivateMethodsBesides']);
    }

    /**
     * Not supported.
     */
    public function toHavePrivateMethods(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePrivateMethods']);
    }

    /**
     * Asserts that the given expectation target is enum.
     */
    public function toBeEnum(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->isEnum(),
            'to be enum',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are enums.
     */
    public function toBeEnums(): ArchExpectation
    {
        return $this->toBeEnum();
    }

    /**
     * Asserts that the given expectation target is a class.
     */
    public function toBeClass(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => class_exists($object->name) && ! enum_exists($object->name),
            'to be class',
            FileLineFinder::where(fn (string $line): bool => true),
        );
    }

    /**
     * Asserts that the given expectation targets are classes.
     */
    public function toBeClasses(): ArchExpectation
    {
        return $this->toBeClass();
    }

    /**
     * Asserts that the given expectation target is interface.
     */
    public function toBeInterface(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->isInterface(),
            'to be interface',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are interfaces.
     */
    public function toBeInterfaces(): ArchExpectation
    {
        return $this->toBeInterface();
    }

    /**
     * Asserts that the given expectation target to be subclass of the given class.
     */
    public function toExtend(string $class): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && ($class === $object->reflectionClass->getName() || $object->reflectionClass->isSubclassOf($class)),
            sprintf("to extend '%s'", $class),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to be have a parent class.
     */
    public function toExtendNothing(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => $object->reflectionClass->getParentClass() === false,
            'to extend nothing',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to use the given trait.
     */
    public function toUseTrait(string $trait): ArchExpectation
    {
        return $this->toUseTraits($trait);
    }

    /**
     * Asserts that the given expectation target to use the given traits.
     *
     * @param  array<int, string>|string  $traits
     */
    public function toUseTraits(array|string $traits): ArchExpectation
    {
        $traits = is_array($traits) ? $traits : [$traits];

        return Targeted::make(
            $this,
            function (ObjectDescription $object) use ($traits): bool {
                foreach ($traits as $trait) {
                    if (isset($object->reflectionClass) === false) {
                        return false;
                    }

                    if (! in_array($trait, $object->reflectionClass->getTraitNames(), true)) {
                        return false;
                    }
                }

                return true;
            },
            "to use traits '".implode("', '", $traits)."'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to not implement any interfaces.
     */
    public function toImplementNothing(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->getInterfaceNames() === [],
            'to implement nothing',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to only implement the given interfaces.
     *
     * @param  array<int, string>|string  $interfaces
     */
    public function toOnlyImplement(array|string $interfaces): ArchExpectation
    {
        $interfaces = is_array($interfaces) ? $interfaces : [$interfaces];

        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass)
                && (count($interfaces) === count($object->reflectionClass->getInterfaceNames()))
                && array_diff($interfaces, $object->reflectionClass->getInterfaceNames()) === [],
            "to only implement '".implode("', '", $interfaces)."'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to have the given prefix.
     */
    public function toHavePrefix(string $prefix): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && str_starts_with($object->reflectionClass->getShortName(), $prefix),
            "to have prefix '{$prefix}'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to have the given suffix.
     */
    public function toHaveSuffix(string $suffix): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && str_ends_with($object->reflectionClass->getName(), $suffix),
            "to have suffix '{$suffix}'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target to implement the given interfaces.
     *
     * @param  array<int, string>|string  $interfaces
     */
    public function toImplement(array|string $interfaces): ArchExpectation
    {
        $interfaces = is_array($interfaces) ? $interfaces : [$interfaces];

        return Targeted::make(
            $this,
            function (ObjectDescription $object) use ($interfaces): bool {
                foreach ($interfaces as $interface) {
                    if (! isset($object->reflectionClass) || ! $object->reflectionClass->implementsInterface($interface)) {
                        return false;
                    }
                }

                return true;
            },
            "to implement '".implode("', '", $interfaces)."'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target "only" use on the given dependencies.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toOnlyUse(array|string $targets): ArchExpectation
    {
        return ToOnlyUse::make($this, $targets);
    }

    /**
     * Asserts that the given expectation target does not use any dependencies.
     */
    public function toUseNothing(): ArchExpectation
    {
        return ToUseNothing::make($this);
    }

    /**
     * Asserts that the source code of the given expectation target does not include suspicious characters.
     */
    public function toHaveSuspiciousCharacters(): ArchExpectation
    {
        throw InvalidExpectation::fromMethods(['toHaveSuspiciousCharacters']);
    }

    /**
     * Not supported.
     */
    public function toBeUsed(): void
    {
        throw InvalidExpectation::fromMethods(['toBeUsed']);
    }

    /**
     * Asserts that the given expectation dependency is used by the given targets.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toBeUsedIn(array|string $targets): ArchExpectation
    {
        return ToBeUsedIn::make($this, $targets);
    }

    /**
     * Asserts that the given expectation dependency is "only" used by the given targets.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toOnlyBeUsedIn(array|string $targets): ArchExpectation
    {
        return ToOnlyBeUsedIn::make($this, $targets);
    }

    /**
     * Asserts that the given expectation dependency is not used.
     */
    public function toBeUsedInNothing(): ArchExpectation
    {
        return ToBeUsedInNothing::make($this);
    }

    /**
     * Asserts that the given expectation dependency is an invokable class.
     */
    public function toBeInvokable(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->hasMethod('__invoke'),
            'to be invokable',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class'))
        );
    }

    /**
     * Asserts that the given expectation is iterable and contains snake_case keys.
     *
     * @return self<TValue>
     */
    public function toHaveSnakeCaseKeys(string $message = ''): self
    {
        if (! is_iterable($this->value)) {
            InvalidExpectationValue::expected('iterable');
        }

        foreach ($this->value as $k => $item) {
            if (is_string($k)) {
                $this->and($k)->toBeSnakeCase($message);
            }

            if (is_array($item)) {
                $this->and($item)->toHaveSnakeCaseKeys($message);
            }
        }

        return $this;
    }

    /**
     * Asserts that the given expectation is iterable and contains kebab-case keys.
     *
     * @return self<TValue>
     */
    public function toHaveKebabCaseKeys(string $message = ''): self
    {
        if (! is_iterable($this->value)) {
            InvalidExpectationValue::expected('iterable');
        }

        foreach ($this->value as $k => $item) {
            if (is_string($k)) {
                $this->and($k)->toBeKebabCase($message);
            }

            if (is_array($item)) {
                $this->and($item)->toHaveKebabCaseKeys($message);
            }
        }

        return $this;
    }

    /**
     * Asserts that the given expectation is iterable and contains camelCase keys.
     *
     * @return self<TValue>
     */
    public function toHaveCamelCaseKeys(string $message = ''): self
    {
        if (! is_iterable($this->value)) {
            InvalidExpectationValue::expected('iterable');
        }

        foreach ($this->value as $k => $item) {
            if (is_string($k)) {
                $this->and($k)->toBeCamelCase($message);
            }

            if (is_array($item)) {
                $this->and($item)->toHaveCamelCaseKeys($message);
            }
        }

        return $this;
    }

    /**
     * Asserts that the given expectation is iterable and contains StudlyCase keys.
     *
     * @return self<TValue>
     */
    public function toHaveStudlyCaseKeys(string $message = ''): self
    {
        if (! is_iterable($this->value)) {
            InvalidExpectationValue::expected('iterable');
        }

        foreach ($this->value as $k => $item) {
            if (is_string($k)) {
                $this->and($k)->toBeStudlyCase($message);
            }

            if (is_array($item)) {
                $this->and($item)->toHaveStudlyCaseKeys($message);
            }
        }

        return $this;
    }

    /**
     * Asserts that the given expectation target to have the given attribute.
     */
    public function toHaveAttribute(string $attribute): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->getAttributes($attribute) !== [],
            "to have attribute '{$attribute}'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation target has a constructor method.
     */
    public function toHaveConstructor(): ArchExpectation
    {
        return $this->toHaveMethod('__construct');
    }

    /**
     * Asserts that the given expectation target has a destructor method.
     */
    public function toHaveDestructor(): ArchExpectation
    {
        return $this->toHaveMethod('__destruct');
    }

    /**
     * Asserts that the given expectation target is a backed enum of given type.
     */
    private function toBeBackedEnum(string $backingType): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass)
                && $object->reflectionClass->isEnum()
                && (new ReflectionEnum($object->name))->isBacked() // @phpstan-ignore-line
                && (string) (new ReflectionEnum($object->name))->getBackingType() === $backingType, // @phpstan-ignore-line
            'to be '.$backingType.' backed enum',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * Asserts that the given expectation targets are string backed enums.
     */
    public function toBeStringBackedEnums(): ArchExpectation
    {
        return $this->toBeStringBackedEnum();
    }

    /**
     * Asserts that the given expectation targets are int backed enums.
     */
    public function toBeIntBackedEnums(): ArchExpectation
    {
        return $this->toBeIntBackedEnum();
    }

    /**
     * Asserts that the given expectation target is a string backed enum.
     */
    public function toBeStringBackedEnum(): ArchExpectation
    {
        return $this->toBeBackedEnum('string');
    }

    /**
     * Asserts that the given expectation target is an int backed enum.
     */
    public function toBeIntBackedEnum(): ArchExpectation
    {
        return $this->toBeBackedEnum('int');
    }
}
