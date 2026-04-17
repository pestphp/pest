<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Pest\Exceptions\ShouldNotHappen;
use Pest\TestSuite;

/**
 * @internal
 */
final class SnapshotRepository
{
    /** @var array<string, int> */
    private static array $expectationsCounter = [];

    /**
     * Creates a snapshot repository instance.
     */
    public function __construct(
        private readonly string $rootPath,
        private readonly string $testsPath,
        private readonly string $snapshotsPath,
    ) {}

    /**
     * Checks if the snapshot exists.
     */
    public function has(): bool
    {
        return file_exists($this->getSnapshotFilename());
    }

    /**
     * Gets the snapshot.
     *
     * @return array{0: string, 1: string}
     *
     * @throws ShouldNotHappen
     */
    public function get(): array
    {
        $contents = file_get_contents($snapshotFilename = $this->getSnapshotFilename());

        if ($contents === false) {
            throw ShouldNotHappen::fromMessage('Snapshot file could not be read.');
        }

        $snapshot = str_replace(dirname($this->testsPath).'/', '', $snapshotFilename);

        return [$snapshot, $contents];
    }

    /**
     * Saves the given snapshot for the given test case.
     */
    public function save(string $snapshot): string
    {
        $snapshotFilename = $this->getSnapshotFilename();

        $directory = dirname($snapshotFilename);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
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
     * Gets the snapshot's "filename".
     */
    private function getSnapshotFilename(): string
    {
        $testFile = TestSuite::getInstance()->getFilename();

        if (str_starts_with($testFile, $this->testsPath)) {
            // if the test file is in the tests directory
            $startPath = $this->testsPath;
        } else {
            // if the test file is in the app, src, etc. directory
            $startPath = $this->rootPath;
        }

        // relative path: we use substr() and not str_replace() to remove the start path
        // for instance, if the $startPath is /app/ and the $testFile is /app/app/tests/Unit/ExampleTest.php, we should only remove the first /app/ from the path
        $relativePath = substr($testFile, strlen($startPath));

        // remove extension from filename
        $relativePath = substr($relativePath, 0, (int) strrpos($relativePath, '.'));

        $description = TestSuite::getInstance()->getDescription();

        if ($this->getCurrentSnapshotCounter() > 1) {
            $description .= '__'.$this->getCurrentSnapshotCounter();
        }

        return sprintf('%s/%s.snap', $this->testsPath.'/'.$this->snapshotsPath.$relativePath, $description);
    }

    private function getCurrentSnapshotKey(): string
    {
        return TestSuite::getInstance()->getFilename().'###'.TestSuite::getInstance()->getDescription();
    }

    private function getCurrentSnapshotCounter(): int
    {
        return self::$expectationsCounter[$this->getCurrentSnapshotKey()] ?? 0;
    }

    public function startNewExpectation(): void
    {
        $key = $this->getCurrentSnapshotKey();

        if (! isset(self::$expectationsCounter[$key])) {
            self::$expectationsCounter[$key] = 0;
        }

        self::$expectationsCounter[$key]++;
    }
}
