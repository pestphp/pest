<?php

declare(strict_types=1);

namespace Pest\Repositories;

use InvalidArgumentException;
use Pest\Exceptions\ShouldNotHappen;
use Pest\TestSuite;

/**
 * @internal
 */
final class SnapshotRepository
{
    private static ?string $key = null;

    private static int $ordinal = 0;

    public function __construct(
        private readonly string $rootPath,
        private readonly string $testsPath,
        private readonly string $snapshotsPath,
    ) {}

    public function current(): Snapshot
    {
        $this->synchronize();

        return $this->snapshot(self::$ordinal > 1 ? '__'.self::$ordinal : '');
    }

    public function next(): Snapshot
    {
        $this->synchronize();

        self::$ordinal++;

        return $this->current();
    }

    public function named(string $name): Snapshot
    {
        $this->synchronize();

        $suffix = trim((string) preg_replace('/[^\w-]+/', '_', $name), '_');

        if ($suffix === '') {
            throw new InvalidArgumentException('The snapshot name must contain at least one alphanumeric character.');
        }

        return $this->snapshot('__'.$suffix);
    }

    public function forget(): void
    {
        self::$key = null;
        self::$ordinal = 0;
    }

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

    private function snapshot(string $suffix): Snapshot
    {
        $testFile = TestSuite::getInstance()->getFilename();

        $startPath = str_starts_with($testFile, $this->testsPath) ? $this->testsPath : $this->rootPath;

        $relativePath = substr($testFile, strlen($startPath));
        $relativePath = substr($relativePath, 0, (int) strrpos($relativePath, '.'));

        return new Snapshot(
            sprintf(
                '%s/%s%s.snap',
                $this->testsPath.'/'.$this->snapshotsPath.$relativePath,
                TestSuite::getInstance()->getDescription(),
                $suffix,
            ),
            dirname($this->testsPath).'/',
        );
    }

    private function synchronize(): void
    {
        $key = TestSuite::getInstance()->getFilename().'###'.TestSuite::getInstance()->getDescription();

        if (self::$key === $key) {
            return;
        }

        self::$key = $key;
        self::$ordinal = 0;
    }

    /**
     * @deprecated Use `next` and `current` instead.
     */
    public function startNewExpectation(): void
    {
        $this->next();
    }

    /**
     * @deprecated Use `current` instead.
     */
    public function has(): bool
    {
        return $this->current()->exists();
    }

    /**
     * @deprecated Use `current` instead.
     *
     * @return array{0: string, 1: string}
     *
     * @throws ShouldNotHappen
     */
    public function get(): array
    {
        $snapshot = $this->current();

        return [$snapshot->path(), $snapshot->read()];
    }

    /**
     * @deprecated Use `current` instead.
     */
    public function save(string $snapshot): string
    {
        return $this->current()->write($snapshot)->path();
    }

    /**
     * @deprecated Use `current` instead.
     */
    public function filename(): string
    {
        return $this->current()->path();
    }
}
