<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Contracts;

/**
 * @internal
 */
interface Lockfile
{
    public function applies(string $filename): bool;

    public function fingerprint(string $contents): ?string;
}
