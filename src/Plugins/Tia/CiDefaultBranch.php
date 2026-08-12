<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia\Contracts\Ci;

/**
 * @internal
 */
final class CiDefaultBranch
{
    /**
     * @var array<int, class-string<Ci>>
     */
    private const array CIS = [
        Cis\GitLab::class,
        Cis\GitHub::class,
    ];

    public static function detect(): ?string
    {
        foreach (self::CIS as $class) {
            $branch = (new $class)->defaultBranch();

            if ($branch !== null) {
                return $branch;
            }
        }

        return null;
    }
}
