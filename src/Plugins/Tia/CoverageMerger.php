<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\Container;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Serialization\Unserializer;
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

        $changedFiles = self::consumeChangedFiles($state);

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

        self::dropChangedFilesFromCached($cached, $changedFiles);
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

    /**
     * Cached coverage of a changed file was measured against old file contents,
     * so its line numbers no longer match the source on disk. Every test that
     * covered the file is part of the affected set and has just been re-run,
     * meaning the current report already carries the file's entire coverage —
     * anything cached for it is stale and must not survive the merge.
     *
     * @param  array<int, string>  $changedFiles
     */
    private static function dropChangedFilesFromCached(CodeCoverage $cached, array $changedFiles): void
    {
        if ($changedFiles === []) {
            return;
        }

        $data = $cached->getData();

        $lineCoverage = $data->lineCoverage();
        $functionCoverage = $data->functionCoverage();

        foreach ($changedFiles as $file) {
            unset($lineCoverage[$file], $functionCoverage[$file]);
        }

        $data->setLineCoverage($lineCoverage);
        $data->setFunctionCoverage($functionCoverage);
    }

    /**
     * @return array<int, string>
     */
    private static function consumeChangedFiles(State $state): array
    {
        $encoded = $state->read(Tia::KEY_COVERAGE_CHANGED);

        if ($encoded === null) {
            return [];
        }

        $state->delete(Tia::KEY_COVERAGE_CHANGED);

        $decoded = json_decode($encoded, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, is_string(...)));
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

        // Legacy `--coverage-php` format: a serialized `CodeCoverage` object.
        if ($value instanceof CodeCoverage) {
            return $value;
        }

        // Since phpunit/php-code-coverage 14, `--coverage-php` writes the report
        // as a serialized array (`['codeCoverage' => ..., 'testResults' => ...,
        // 'basePath' => ...]`) rather than a `CodeCoverage` object, so it has to
        // be rebuilt into one before it can be merged.
        return self::coverageFromSerializedData($reportPath);
    }

    private static function coverageFromSerializedData(string $reportPath): ?CodeCoverage
    {
        if ($reportPath === '') {
            return null;
        }

        try {
            $serialized = new Unserializer()->unserialize($reportPath);
        } catch (Throwable) {
            return null;
        }

        $data = $serialized['codeCoverage'];
        $basePath = $serialized['basePath'];

        if ($basePath !== '') {
            foreach ($data->coveredFiles() as $relativePath) {
                $data->renameFile($relativePath, $basePath.DIRECTORY_SEPARATOR.$relativePath);
            }
        }

        $coverage = self::emptyCoverage();

        if (! $coverage instanceof CodeCoverage) {
            return null;
        }

        $coverage->setData($data);
        $coverage->setTests($serialized['testResults']);

        return $coverage;
    }

    private static function emptyCoverage(): ?CodeCoverage
    {
        try {
            $filter = new Filter;

            return new CodeCoverage(new Selector()->forLineCoverage($filter), $filter);
        } catch (Throwable) {
            return null;
        }
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
