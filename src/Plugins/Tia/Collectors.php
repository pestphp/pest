<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia\Edges\BladeEdges;
use Pest\Plugins\Tia\Edges\InertiaEdges;

/**
 * @internal
 */
final class Collectors
{
    /** @var list<class-string> */
    private const array COLLECTORS = [
        BladeEdges::class,
        TableTracker::class,
        InertiaEdges::class,
    ];

    public static function armAll(Recorder $recorder): void
    {
        foreach (self::COLLECTORS as $collector) {
            $collector::arm($recorder);
        }
    }
}
