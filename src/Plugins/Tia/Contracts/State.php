<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Contracts;

/**
 * @internal
 */
interface State
{
    public function read(string $key): ?string;

    public function write(string $key, string $content): bool;

    public function delete(string $key): bool;

    public function exists(string $key): bool;

    /**
     * @return list<string>
     */
    public function keysWithPrefix(string $prefix): array;
}
