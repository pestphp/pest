<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Mockery;
use Pest\Exceptions\AfterEachAlreadyExist;
use Pest\Support\ChainableClosure;
use Pest\Support\NullClosure;

/**
 * @internal
 */
final class AfterEachRepository
{
    /**
     * @var array<string, Closure>
     */
    private array $state = [];

    /**
     * Sets a after each closure.
     */
    public function set(string $filename, Closure $closure): void
    {
        if (array_key_exists($filename, $this->state)) {
            throw new AfterEachAlreadyExist($filename);
        }

        $this->state[$filename] = $closure;
    }

    /**
     * Gets an after each closure by the given filename.
     */
    public function get(string $filename): Closure
    {
        $afterEach = $this->state[$filename] ?? NullClosure::create();

        return ChainableClosure::from(function (): void {
            if (class_exists(Mockery::class)) {
                if ($container = Mockery::getContainer()) {
                    /* @phpstan-ignore-next-line */
                    $this->addToAssertionCount($container->mockery_getExpectationCount());
                }

                Mockery::close();
            }
        }, $afterEach);
    }
}
