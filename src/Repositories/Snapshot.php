<?php

declare(strict_types=1);

namespace Pest\Repositories;

use Pest\Exceptions\ShouldNotHappen;
use RuntimeException;

/**
 * @internal
 */
final readonly class Snapshot
{
    public function __construct(
        private string $filename,
        private string $basePath,
    ) {}

    public function exists(): bool
    {
        return file_exists($this->filename);
    }

    public function path(): string
    {
        return str_replace($this->basePath, '', $this->filename);
    }

    /**
     * @throws ShouldNotHappen
     */
    public function read(): string
    {
        $contents = file_get_contents($this->filename);

        if ($contents === false) {
            throw ShouldNotHappen::fromMessage('Snapshot file could not be read.');
        }

        return $contents;
    }

    public function write(string $contents): self
    {
        $directory = dirname($this->filename);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (@file_put_contents($this->filename, $contents) !== strlen($contents)) {
            throw new RuntimeException(sprintf('Unable to write snapshot at [%s].', $this->path()));
        }

        return $this;
    }

    /**
     * @throws ShouldNotHappen
     */
    public function matches(string $value): bool
    {
        return $this->normalize($this->read()) === $this->normalize($value);
    }

    public function normalize(string $value): string
    {
        return strtr($value, ["\r\n" => "\n", "\r" => "\n"]);
    }
}
