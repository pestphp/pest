<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\Container;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Throwable;

/**
 * @internal
 */
final class CoverageMerger
{
    public static function applyIfMarked(string $reportPath): void
    {
        $state = self::state();

        if (! $state->exists(Tia::KEY_COVERAGE_MARKER)) {
            return;
        }

        $state->delete(Tia::KEY_COVERAGE_MARKER);

        $cachedBytes = $state->read(Tia::KEY_COVERAGE_CACHE);

        if ($cachedBytes === null) {
            $current = self::requireCoverage($reportPath);

            if ($current instanceof CodeCoverage) {
                self::primeUncoveredFiles($current);
                $state->write(Tia::KEY_COVERAGE_CACHE, self::compress(serialize($current)));
            }

            return;
        }

        $decoded = self::decompress($cachedBytes);

        if ($decoded === null) {
            $state->delete(Tia::KEY_COVERAGE_CACHE);

            return;
        }

        $cached = self::unserializeCoverage($decoded);
        $current = self::requireCoverage($reportPath);

        if (! $cached instanceof CodeCoverage || ! $current instanceof CodeCoverage) {
            return;
        }

        self::primeUncoveredFiles($cached);
        self::primeUncoveredFiles($current);

        self::stripCurrentTestsFromCached($cached, $current);

        $cached->merge($current);

        $serialised = serialize($cached);

        @file_put_contents(
            $reportPath,
            '<?php return unserialize('.var_export($serialised, true).");\n",
        );
        $state->write(Tia::KEY_COVERAGE_CACHE, self::compress($serialised));
    }

    private static function primeUncoveredFiles(CodeCoverage $coverage): void
    {
        $coverage->getData(false);
    }

    private static function compress(string $bytes): string
    {
        $compressed = @gzencode($bytes);

        return $compressed === false ? $bytes : $compressed;
    }

    private static function decompress(string $bytes): ?string
    {
        $decoded = @gzdecode($bytes);

        return $decoded === false ? null : $decoded;
    }

    private static function stripCurrentTestsFromCached(CodeCoverage $cached, CodeCoverage $current): void
    {
        $currentIds = self::collectTestIds($current);

        if ($currentIds === []) {
            return;
        }

        $cachedData = $cached->getData();
        $lineCoverage = $cachedData->lineCoverage();

        foreach ($lineCoverage as $file => $lines) {
            foreach ($lines as $line => $ids) {
                if ($ids === null) {
                    continue;
                }
                if ($ids === []) {
                    continue;
                }
                $filtered = array_values(array_diff($ids, $currentIds));

                if ($filtered !== $ids) {
                    $lineCoverage[$file][$line] = $filtered;
                }
            }
        }

        $cachedData->setLineCoverage($lineCoverage);
    }

    /**
     * @return array<int, string>
     */
    private static function collectTestIds(CodeCoverage $coverage): array
    {
        $ids = [];

        foreach ($coverage->getData()->lineCoverage() as $lines) {
            foreach ($lines as $hits) {
                if ($hits === null) {
                    continue;
                }

                foreach ($hits as $id) {
                    $ids[$id] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private static function state(): State
    {
        $state = Container::getInstance()->get(State::class);
        assert($state instanceof State);

        return $state;
    }

    private static function requireCoverage(string $reportPath): ?CodeCoverage
    {
        if (! is_file($reportPath)) {
            return null;
        }

        try {
            /** @var mixed $value */
            $value = require $reportPath;
        } catch (Throwable) {
            return null;
        }

        return $value instanceof CodeCoverage ? $value : null;
    }

    private static function unserializeCoverage(string $bytes): ?CodeCoverage
    {
        try {
            $value = @unserialize($bytes);
        } catch (Throwable) {
            return null;
        }

        return $value instanceof CodeCoverage ? $value : null;
    }
}
