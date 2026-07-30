<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Support\Container;
use Pest\TestSuite;
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

        $cached = self::readCache($state);

        if (! $cached instanceof CodeCoverage) {
            $current = self::requireCoverage($reportPath);

            if ($current instanceof CodeCoverage) {
                self::primeUncoveredFiles($current);
                $state->write(Tia::KEY_COVERAGE_CACHE, self::compress(self::serializeRelativeToProjectRoot($current)));
            }

            return;
        }

        $current = self::requireCoverage($reportPath);

        if (! $current instanceof CodeCoverage) {
            return;
        }

        self::primeUncoveredFiles($cached);
        self::primeUncoveredFiles($current);

        self::stripCurrentTestsFromCached($cached, $current);

        $cached->merge($current);

        @file_put_contents(
            $reportPath,
            '<?php return unserialize('.var_export(serialize($cached), true).");\n",
        );
        $state->write(Tia::KEY_COVERAGE_CACHE, self::compress(self::serializeRelativeToProjectRoot($cached)));
    }

    /**
     * Reads the cached baseline coverage and maps it onto this machine's
     * project root. An unreadable cache — or one recorded on another machine
     * that cannot be mapped — is dropped so the current run can seed a fresh
     * one, instead of poisoning the merged report with foreign paths.
     */
    private static function readCache(State $state): ?CodeCoverage
    {
        $bytes = $state->read(Tia::KEY_COVERAGE_CACHE);

        if ($bytes === null) {
            return null;
        }

        $decoded = self::decompress($bytes);
        $cached = $decoded === null ? null : self::unserializeCoverage($decoded);

        if ($cached instanceof CodeCoverage) {
            $cached = self::rebaseOntoProjectRoot($cached);
        }

        if (! $cached instanceof CodeCoverage) {
            $state->delete(Tia::KEY_COVERAGE_CACHE);

            return null;
        }

        return $cached;
    }

    /**
     * The cache stores file paths relative to the project root so that a
     * baseline recorded on one machine (e.g. CI) stays mergeable on another.
     * Relative entries are mapped onto the local root here; absolute entries
     * under the local root are kept (caches written by previous versions on
     * this machine), while absolute entries of another machine make the whole
     * cache unusable — a report built from mixed roots contains no resolvable
     * file at all.
     */
    private static function rebaseOntoProjectRoot(CodeCoverage $coverage): ?CodeCoverage
    {
        $root = self::projectRootPrefix();
        $data = $coverage->getData(true);

        foreach ($data->coveredFiles() as $file) {
            if (self::isAbsolutePath($file)) {
                if (! str_starts_with($file, $root)) {
                    return null;
                }

                continue;
            }

            $data->renameFile($file, $root.str_replace('/', DIRECTORY_SEPARATOR, $file));
        }

        $coverage->clearCache();

        return $coverage;
    }

    private static function serializeRelativeToProjectRoot(CodeCoverage $coverage): string
    {
        $root = self::projectRootPrefix();
        $data = $coverage->getData(true);

        foreach ($data->coveredFiles() as $file) {
            if (! str_starts_with($file, $root)) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root)));

            if ($relative === '') {
                continue;
            }

            $data->renameFile($file, $relative);
        }

        $coverage->clearCache();

        return serialize($coverage);
    }

    private static function projectRootPrefix(): string
    {
        return rtrim(TestSuite::getInstance()->rootPath, '/\\').DIRECTORY_SEPARATOR;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1;
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
