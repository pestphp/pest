<?php

declare(strict_types=1);

namespace Pest;

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
use Pest\Arch\Support\Composer;
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
use ReflectionFunction;
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
     * @param  TValue  $value
     */
    public function __construct(
        public mixed $value
    ) {
        //
    }

    /**
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
     * @return self<TValue>
     */
    public function dump(mixed ...$arguments): self
    {
        if (function_exists('dump')) {
            dump($this->value, ...$arguments);
        } else {
            var_dump($this->value, ...$arguments);
        }

        return $this;
    }

    public function dd(mixed ...$arguments): never
    {
        if (function_exists('dd')) {
            dd($this->value, ...$arguments);
        }

        if (getenv('PARATEST') !== false || isset($_SERVER['COLLISION_PRINTER'])) {
            ob_start();
            var_dump($this->value, ...$arguments);
            $output = ob_get_clean();

            throw new ExpectationFailedException($output);
        }

        var_dump($this->value, ...$arguments);

        exit(1);
    }

    /**
     * @param  (Closure(TValue): bool)|bool  $condition
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
     * @param  (Closure(TValue): bool)|bool  $condition
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
     * @return OppositeExpectation<TValue>
     */
    public function not(): OppositeExpectation
    {
        return new OppositeExpectation($this);
    }

    /**
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
                new self($value)->toEqual($callbacks[$index]);
            }

            $index = isset($callbacks[$index + 1]) ? $index + 1 : 0;
        }

        if ($valuesCount < count($callbacks)) {
            throw new OutOfRangeException('Sequence expectations are more than the iterable items.');
        }

        return $this;
    }

    /**
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
                    'Method [%s] does not exist in [%s].',
                    $method,
                    gettype($this->value)
                ));
            }

            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, call_user_func_array($this->value->$method(...), $parameters));
        }

        $closure = $this->getExpectationClosure($method);
        $reflectionClosure = new ReflectionFunction($closure);
        $expectation = $reflectionClosure->getClosureThis();
        $parameters = $this->positionalParameters($reflectionClosure, $parameters);

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
     * @param  array<array-key, mixed>  $parameters
     * @return array<array-key, mixed>
     */
    private function positionalParameters(ReflectionFunction $closure, array $parameters): array
    {
        if ($parameters === [] || array_is_list($parameters)) {
            return $parameters;
        }

        $positional = [];

        foreach ($closure->getParameters() as $position => $parameter) {
            if ($parameter->isVariadic()) {
                return $parameters;
            }

            $name = $parameter->getName();

            if (array_key_exists($position, $parameters)) {
                $positional[] = $parameters[$position];

                unset($parameters[$position]);
            } elseif (array_key_exists($name, $parameters)) {
                $positional[] = $parameters[$name];

                unset($parameters[$name]);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $positional[] = $parameter->getDefaultValue();
            } else {
                return $parameters;
            }
        }

        return $parameters === [] ? $positional : $parameters;
    }

    /**
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

    public static function hasMethod(string $name): bool
    {
        return method_exists(self::class, $name)
            || method_exists(Mixins\Expectation::class, $name)
            || self::hasExtend($name);
    }

    public function any(): Any
    {
        return new Any;
    }

    /**
     * @param  array<int, string>|string  $targets
     */
    public function toUse(array|string $targets): ArchExpectation
    {
        return ToUse::make($this, $targets);
    }

    public function toHaveFileSystemPermissions(string $permissions): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => substr(sprintf('%o', fileperms($object->path)), -4) === $permissions,
            sprintf('permissions to be [%s]', $permissions),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

    public function toHaveLineCountLessThan(int $lines): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => count(file($object->path)) < $lines, // @phpstan-ignore-line
            sprintf('to have less than %d lines of code', $lines),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

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

    public function toUseStrictTypes(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => (bool) preg_match('/^<\?php\s*(\/\*[\s\S]*?\*\/|\/\/[^\r\n]*(?:\r?\n|$)|\s)*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m', (string) file_get_contents($object->path)),
            'to use strict types',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, '<?php')),
        );
    }

    public function toUseStrictEquality(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => ! str_contains((string) file_get_contents($object->path), ' == ') && ! str_contains((string) file_get_contents($object->path), ' != '),
            'to use strict equality',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, ' == ') || str_contains($line, ' != ')),
        );
    }

    public function toBeFinal(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => ! enum_exists($object->name) && isset($object->reflectionClass) && $object->reflectionClass->isFinal(),
            'to be final',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toBeReadonly(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => ! enum_exists($object->name) && isset($object->reflectionClass) && $object->reflectionClass->isReadOnly() && assert(true), // @phpstan-ignore-line
            'to be readonly',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toBeTrait(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->isTrait(),
            'to be trait',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toBeTraits(): ArchExpectation
    {
        return $this->toBeTrait();
    }

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
     * @param  array<int, string>|string  $method
     */
    public function toHaveMethod(array|string $method): ArchExpectation
    {
        $methods = is_array($method) ? $method : [$method];

        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => count(array_filter($methods, fn (string $method): bool => isset($object->reflectionClass) && $object->reflectionClass->hasMethod($method))) === count($methods),
            sprintf('to have method [%s]', implode('], [', $methods)),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * @param  array<int, string>  $methods
     */
    public function toHaveMethods(array $methods): ArchExpectation
    {
        return $this->toHaveMethod($methods);
    }

    public function toHavePublicMethodsBesides(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePublicMethodsBesides']);
    }

    public function toHavePublicMethods(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePublicMethods']);
    }

    public function toHaveProtectedMethodsBesides(): void
    {
        throw InvalidExpectation::fromMethods(['toHaveProtectedMethodsBesides']);
    }

    public function toHaveProtectedMethods(): void
    {
        throw InvalidExpectation::fromMethods(['toHaveProtectedMethods']);
    }

    public function toHavePrivateMethodsBesides(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePrivateMethodsBesides']);
    }

    public function toHavePrivateMethods(): void
    {
        throw InvalidExpectation::fromMethods(['toHavePrivateMethods']);
    }

    public function toBeCasedCorrectly(): ArchExpectation
    {
        return Targeted::make(
            $this,
            function (ObjectDescription $object): bool {
                if (! isset($object->reflectionClass)) {
                    return false;
                }

                $realPath = realpath($object->path);

                if ($realPath === false) {
                    return false;
                }

                foreach (Composer::allNamespacesWithDirectories() as $directory => $namespace) {
                    if (str_starts_with($realPath, $directory)) {
                        $relativePath = substr($realPath, strlen($directory) + 1);
                        $relativePath = explode('.', $relativePath)[0];
                        $classFromPath = $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

                        return $classFromPath === $object->reflectionClass->getName();
                    }
                }

                return false;
            },
            'to be cased correctly',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toBeEnum(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->isEnum(),
            'to be enum',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toBeEnums(): ArchExpectation
    {
        return $this->toBeEnum();
    }

    public function toBeClass(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => class_exists($object->name) && ! enum_exists($object->name),
            'to be class',
            FileLineFinder::where(fn (string $line): bool => true),
        );
    }

    public function toBeClasses(): ArchExpectation
    {
        return $this->toBeClass();
    }

    public function toBeInterface(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->isInterface(),
            'to be interface',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toBeInterfaces(): ArchExpectation
    {
        return $this->toBeInterface();
    }

    public function toExtend(string $class): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && ($class === $object->reflectionClass->getName() || $object->reflectionClass->isSubclassOf($class)),
            sprintf('to extend [%s]', $class),
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toExtendNothing(): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => $object->reflectionClass->getParentClass() === false,
            'to extend nothing',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toUseTrait(string $trait): ArchExpectation
    {
        return $this->toUseTraits($trait);
    }

    /**
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

                    $currentClass = $object->reflectionClass;
                    $usedTraits = [];

                    do {
                        $classTraits = $currentClass->getTraits();
                        foreach ($classTraits as $traitReflection) {
                            $usedTraits[$traitReflection->getName()] = $traitReflection->getName();

                            $nestedTraits = $traitReflection->getTraits();
                            foreach ($nestedTraits as $nestedTrait) {
                                $usedTraits[$nestedTrait->getName()] = $nestedTrait->getName();
                            }
                        }
                    } while ($currentClass = $currentClass->getParentClass());

                    if (! array_key_exists($trait, $usedTraits)) {
                        return false;
                    }
                }

                return true;
            },
            "to use traits '".implode("', '", $traits)."'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

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

    public function toHavePrefix(string $prefix): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && str_starts_with($object->reflectionClass->getShortName(), $prefix),
            "to have prefix [{$prefix}]",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toHaveSuffix(string $suffix): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && str_ends_with($object->reflectionClass->getName(), $suffix),
            "to have suffix [{$suffix}]",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * @param  array<int, string>|string  $interfaces
     */
    public function toImplement(array|string $interfaces): ArchExpectation
    {
        $interfaces = is_array($interfaces) ? $interfaces : [$interfaces];

        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => array_all($interfaces, fn (string $interface): bool => isset($object->reflectionClass) && $object->reflectionClass->implementsInterface($interface)),
            "to implement '".implode("', '", $interfaces)."'",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    /**
     * @param  array<int, string>|string  $targets
     */
    public function toOnlyUse(array|string $targets): ArchExpectation
    {
        return ToOnlyUse::make($this, $targets);
    }

    public function toUseNothing(): ArchExpectation
    {
        return ToUseNothing::make($this);
    }

    public function toHaveSuspiciousCharacters(): ArchExpectation
    {
        throw InvalidExpectation::fromMethods(['toHaveSuspiciousCharacters']);
    }

    public function toBeUsed(): void
    {
        throw InvalidExpectation::fromMethods(['toBeUsed']);
    }

    /**
     * @param  array<int, string>|string  $targets
     */
    public function toBeUsedIn(array|string $targets): ArchExpectation
    {
        return ToBeUsedIn::make($this, $targets);
    }

    /**
     * @param  array<int, string>|string  $targets
     */
    public function toOnlyBeUsedIn(array|string $targets): ArchExpectation
    {
        return ToOnlyBeUsedIn::make($this, $targets);
    }

    public function toBeUsedInNothing(): ArchExpectation
    {
        return ToBeUsedInNothing::make($this);
    }

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

    public function toHaveAttribute(string $attribute): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass) && $object->reflectionClass->getAttributes($attribute) !== [],
            "to have attribute [{$attribute}]",
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toHaveConstructor(): ArchExpectation
    {
        return $this->toHaveMethod('__construct');
    }

    public function toHaveDestructor(): ArchExpectation
    {
        return $this->toHaveMethod('__destruct');
    }

    private function toBeBackedEnum(string $backingType): ArchExpectation
    {
        return Targeted::make(
            $this,
            fn (ObjectDescription $object): bool => isset($object->reflectionClass)
                && $object->reflectionClass->isEnum()
                && new ReflectionEnum($object->name)->isBacked() // @phpstan-ignore-line
                && (string) new ReflectionEnum($object->name)->getBackingType() === $backingType, // @phpstan-ignore-line
            'to be '.$backingType.' backed enum',
            FileLineFinder::where(fn (string $line): bool => str_contains($line, 'class')),
        );
    }

    public function toBeStringBackedEnums(): ArchExpectation
    {
        return $this->toBeStringBackedEnum();
    }

    public function toBeIntBackedEnums(): ArchExpectation
    {
        return $this->toBeIntBackedEnum();
    }

    public function toBeStringBackedEnum(): ArchExpectation
    {
        return $this->toBeBackedEnum('string');
    }

    public function toBeIntBackedEnum(): ArchExpectation
    {
        return $this->toBeBackedEnum('int');
    }
}
