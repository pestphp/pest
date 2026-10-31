<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

/**
 * @internal
 */
final class LoggingFileWrapper
{
    private const int STREAM_OPEN_FOR_INCLUDE = 0x00000080;

    /**
     * @var array<int, array{path: string, including: bool}>
     */
    public static array $opened = [];

    /**
     * @var resource|null
     */
    public mixed $context = null;

    /**
     * @var resource
     */
    private mixed $resource;

    public static function enable(): void
    {
        self::$opened = [];

        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
    }

    public static function disable(): void
    {
        stream_wrapper_restore('file');
    }

    public function stream_open(string $path, string $mode, int $options): bool
    {
        self::disable();

        try {
            $resource = @fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0);
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }

        if ($resource === false) {
            return false;
        }

        self::$opened[] = ['path' => $path, 'including' => ($options & self::STREAM_OPEN_FOR_INCLUDE) !== 0];

        $this->resource = $resource;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->resource, max(1, $count));
    }

    public function stream_write(string $data): int|false
    {
        return fwrite($this->resource, $data);
    }

    public function stream_eof(): bool
    {
        return feof($this->resource);
    }

    public function stream_close(): void
    {
        fclose($this->resource);
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->resource);
    }

    public function stream_set_option(): bool
    {
        return false;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path): array|false
    {
        self::disable();

        try {
            return @stat($path);
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }

    public function dir_opendir(string $path): bool
    {
        self::disable();

        try {
            $resource = @opendir($path);
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }

        if ($resource === false) {
            return false;
        }

        $this->resource = $resource;

        return true;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->resource);
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->resource);

        return true;
    }

    public function dir_closedir(): bool
    {
        closedir($this->resource);

        return true;
    }

    public function unlink(string $path): bool
    {
        self::disable();

        try {
            return @unlink($path);
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }
}
