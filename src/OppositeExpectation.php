<?php

declare(strict_types=1);

namespace Pest;

use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 *
 * @mixin Expectation
 */
final class OppositeExpectation
{
    /**
     * @var Expectation
     */
    private $original;

    /**
     * Creates a new opposite expectation.
     */
    public function __construct(Expectation $original)
    {
        $this->original = $original;
    }

    /**
     * Handle dynamic method calls into the original expectation.
     *
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): Expectation
    {
        try {
            /* @phpstan-ignore-next-line */
            $this->original->{$name}(...$arguments);
        } catch (ExpectationFailedException $e) {
            return $this->original;
        }

        throw new ExpectationFailedException(sprintf('@todo'));
    }

    /**
     * Handle dynamic properties gets into the original expectation.
     */
    public function __get(string $name): Expectation
    {
        try {
            /* @phpstan-ignore-next-line */
            $this->original->{$name};
        } catch (ExpectationFailedException $e) {
            return $this->original;
        }

        throw new ExpectationFailedException(sprintf('@todo'));
    }
}
