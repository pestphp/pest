<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Closure;
use Pest\Exceptions\AfterAllAlreadyExist;
use Pest\Support\NullClosure;
use Pest\Support\Reflection;

/**
 * @internal
 */
final class AfterAllRepository
{
    /**
     * @var array<string, Closure>
     */
    private $state = [];

    /**
     * Runs the given closure for each after all.
     */
    public function each(callable $each): void
    {
        foreach ($this->state as $filename => $closure) {
            $each($filename, $closure);
        }
    }

    /**
     * Sets a after all closure.
     */
    public function set(Closure $closure): void
    {
        $filename = Reflection::getFileNameFromClosure($closure);

        if (array_key_exists($filename, $this->state)) {
            throw new AfterAllAlreadyExist($filename);
        }

        $this->state[$filename] = $closure;
    }

    /**
     * Gets a after all closure by the given filename.
     */
    public function get(string $filename): Closure
    {
        return $this->state[$filename] ?? NullClosure::create();
    }
}
