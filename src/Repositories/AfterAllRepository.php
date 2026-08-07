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
    private array $state = [];

    public function each(callable $each): void
    {
        foreach ($this->state as $filename => $closure) {
            $each($filename, $closure);
        }
    }

    public function set(Closure $closure): void
    {
        $filename = Reflection::getFileNameFromClosure($closure);

        if (array_key_exists($filename, $this->state)) {
            throw new AfterAllAlreadyExist($filename);
        }

        $this->state[$filename] = $closure;
    }

    public function get(string $filename): Closure
    {
        return $this->state[$filename] ?? NullClosure::create();
    }
}
