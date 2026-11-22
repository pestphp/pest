<?php

declare(strict_types=1);

namespace Pest\Plugins\Parallel\Support;

use ReflectionClass;

/**
 * @internal
 */
final readonly class SlowestFirst
{
    /**
     * @param  array<string, float>  $timingsByFile
     */
    private function __construct(private array $timingsByFile, private float $unknownTiming) {}

    /**
     * @param  array<string, float>  $timingsByClass
     */
    public static function fromClassTimings(array $timingsByClass): self
    {
        $timingsByFile = [];

        foreach ($timingsByClass as $class => $seconds) {
            $file = self::fileOf($class);

            if ($file !== null) {
                $timingsByFile[$file] = ($timingsByFile[$file] ?? 0.0) + $seconds;
            }
        }

        return new self($timingsByFile, self::median(array_values($timingsByFile)));
    }

    /**
     * @template TGroup of non-empty-list<non-empty-string>
     *
     * @param  list<TGroup>  $groups
     * @return list<TGroup>
     */
    public function order(array $groups): array
    {
        if ($this->timingsByFile === []) {
            return $groups;
        }

        $durations = array_map($this->durationOf(...), $groups);
        $positions = array_keys($groups);

        usort($positions, fn (int $left, int $right): int => ($durations[$right] <=> $durations[$left]) ?: $left <=> $right);

        return array_map(fn (int $position): array => $groups[$position], $positions);
    }

    /**
     * @param  non-empty-list<non-empty-string>  $group
     */
    private function durationOf(array $group): float
    {
        $total = 0.0;

        foreach ($group as $file) {
            $total += $this->timingsByFile[$file] ?? $this->unknownTiming;
        }

        return $total;
    }

    private static function fileOf(string $class): ?string
    {
        foreach (['P\\'.$class, $class] as $candidate) {
            if (! class_exists($candidate, false)) {
                continue;
            }

            if (property_exists($candidate, '__filename') && is_string($candidate::$__filename)) {
                return $candidate::$__filename;
            }

            $file = new ReflectionClass($candidate)->getFileName();

            return $file === false ? null : (realpath($file) ?: $file);
        }

        return null;
    }

    /**
     * @param  list<float>  $values
     */
    private static function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);

        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }
}
