<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Pest\Exceptions\ShouldNotHappen;
use Pest\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SnapshotRepository
{
    /**
     * Creates a snapshot repository instance.
     */
    public function __construct(
        readonly private string $testsPath,
        readonly private string $snapshotsPath,
    ) {
    }

    /**
     * Checks if the snapshot exists.
     */
    public function has(TestCase $testCase, string $description): bool
    {
        [$filename, $description] = $this->getFilenameAndDescription($testCase);

        return file_exists($this->getSnapshotFilename($filename, $description));
    }

    /**
     * Gets the snapshot.
     *
     * @return array{0: string, 1: string}
     *
     * @throws ShouldNotHappen
     */
    public function get(TestCase $testCase, string $description): array
    {
        [$filename, $description] = $this->getFilenameAndDescription($testCase);

        $contents = file_get_contents($snapshotFilename = $this->getSnapshotFilename($filename, $description));

        if ($contents === false) {
            throw ShouldNotHappen::fromMessage('Snapshot file could not be read.');
        }

        $snapshot = str_replace(dirname($this->testsPath).'/', '', $snapshotFilename);

        return [$snapshot, $contents];
    }

    /**
     * Saves the given snapshot for the given test case.
     */
    public function save(TestCase $testCase, string $snapshot): string
    {
        [$filename, $description] = $this->getFilenameAndDescription($testCase);

        $snapshotFilename = $this->getSnapshotFilename($filename, $description);

        if (! file_exists(dirname($snapshotFilename))) {
            mkdir(dirname($snapshotFilename), 0755, true);
        }

        file_put_contents($snapshotFilename, $snapshot);

        return str_replace(dirname($this->testsPath).'/', '', $snapshotFilename);
    }

    /**
     * Flushes the snapshots.
     */
    public function flush(): void
    {
        $absoluteSnapshotsPath = $this->testsPath.'/'.$this->snapshotsPath;

        $deleteDirectory = function (string $path) use (&$deleteDirectory): void {
            if (file_exists($path)) {
                $scannedDir = scandir($path);
                assert(is_array($scannedDir));

                $files = array_diff($scannedDir, ['.', '..']);

                foreach ($files as $file) {
                    if (is_dir($path.'/'.$file)) {
                        $deleteDirectory($path.'/'.$file);
                    } else {
                        unlink($path.'/'.$file);
                    }
                }

                rmdir($path);
            }
        };

        if (file_exists($absoluteSnapshotsPath)) {
            $deleteDirectory($absoluteSnapshotsPath);
        }
    }

    /**
     * Gets the snapshot's "filename" and "description".
     *
     * @return array{0: string, 1: string}
     */
    private function getFilenameAndDescription(TestCase $testCase): array
    {
        $filename = (fn () => self::$__filename)->call($testCase, $testCase::class); // @phpstan-ignore-line

        $description = str_replace('__pest_evaluable_', '', $testCase->name());
        $datasetAsString = str_replace('__pest_evaluable_', '', Str::evaluable($testCase->dataSetAsStringWithData()));

        $description = str_replace(' ', '_', $description.$datasetAsString);

        return [$filename, $description];
    }

    /**
     * Gets the snapshot's "filename".
     */
    private function getSnapshotFilename(string $filename, string $description): string
    {
        $relativePath = str_replace($this->testsPath, '', $filename);

        // remove extension from filename
        $relativePath = substr($relativePath, 0, (int) strrpos($relativePath, '.'));

        return sprintf('%s/%s.snap', $this->testsPath.'/'.$this->snapshotsPath.$relativePath, $description);
    }
}
