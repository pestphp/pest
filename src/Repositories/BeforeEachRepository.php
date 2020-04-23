<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\Exceptions\BeforeEachAlreadyExist;
use Pest\Support\NullClosure;

/**
 * @internal
 */
final class BeforeEachRepository
{
    /**
     * @var array<string, Closure>
     */
    private array $state = [];

    /**
     * Sets a before each closure.
     */
    public function set(string $filename, Closure $closure): void
    {
        if (array_key_exists($filename, $this->state)) {
            throw new BeforeEachAlreadyExist($filename);
        }

        $this->state[$filename] = $closure;
    }

    /**
     * Gets a before each closure by the given filename.
     */
    public function get(string $filename): Closure
    {
        return $this->state[$filename] ?? NullClosure::create();
    }
}
