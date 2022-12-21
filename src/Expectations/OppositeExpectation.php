<?php

declare(strict_types=1);

namespace Pest\Expectations;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Arch\Expectations\ToDependOn;
use Pest\Arch\Expectations\ToDependOnNothing;
use Pest\Arch\Expectations\ToOnlyDependOn;
use Pest\Arch\GroupArchExpectation;
use Pest\Arch\SingleArchExpectation;
use Pest\Exceptions\InvalidExpectation;
use Pest\Expectation;
use Pest\Support\Arr;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\Exporter\Exporter;

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
     * Asserts that the layer does not depend on the given layers.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toDependOn(array|string $targets): ArchExpectation
    {
        return GroupArchExpectation::fromExpectations(array_map(function (string $target) : SingleArchExpectation {
            return ToDependOn::make($this->original, $target)->opposite(
                fn () => $this->throwExpectationFailedException('toDependOn', $target),
            );
        }, is_string($targets) ? [$targets] : $targets));
    }

    /**
     * Asserts that the layer does not only depends on the given layers.
     *
     * @param  array<int, string>|string  $targets
     */
    public function toOnlyDependOn(array|string $targets): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toOnlyDependOn']);
    }

    /**
     * Asserts that the layer is depends on at least one layer.
     */
    public function toDependOnNothing(): never
    {
        throw InvalidExpectation::fromMethods(['not', 'toDependOnNothing']);
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
            /* @phpstan-ignore-next-line */
            $this->original->{$name}(...$arguments);
        } catch (ExpectationFailedException) {
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
            $this->original->{$name}; // @phpstan-ignore-line
        } catch (ExpectationFailedException) {  // @phpstan-ignore-line
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

        $exporter = new Exporter();

        $toString = fn ($argument): string => $exporter->shortenedExport($argument);

        throw new ExpectationFailedException(sprintf('Expecting %s not %s %s.', $toString($this->original->value), strtolower((string) preg_replace('/(?<!\ )[A-Z]/', ' $0', $name)), implode(' ', array_map(fn ($argument): string => $toString($argument), $arguments))));
    }
}
