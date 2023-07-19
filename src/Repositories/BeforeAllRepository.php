<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\Exceptions\BeforeAllAlreadyExist;
use Pest\Support\NullClosure;
use Pest\Support\Reflection;

/**
 * @internal
 */
final class BeforeAllRepository
{
    /**
     * @var array<string, Closure>
     */
    private array $state = [];

    /**
     * Runs one before all closure, and unsets it from the repository.
     */
    public function pop(string $filename): Closure
    {
        $closure = $this->get($filename);

        unset($this->state[$filename]);

        return $closure;
    }

    /**
     * Sets a before all closure.
     */
    public function set(Closure $closure): void
    {
        $filename = Reflection::getFileNameFromClosure($closure);

        if (array_key_exists($filename, $this->state)) {
            throw new BeforeAllAlreadyExist($filename);
        }

        $this->state[$filename] = $closure;
    }

    /**
     * Gets a before all closure by the given filename.
     */
    public function get(string $filename): Closure
    {
        return $this->state[$filename] ?? NullClosure::create();
    }
}
