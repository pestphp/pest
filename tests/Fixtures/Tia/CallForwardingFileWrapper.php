<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

use Closure;

/**
 * @internal
 */
final class CallForwardingFileWrapper
{
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
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
    }

    public static function disable(): void
    {
        stream_wrapper_restore('file');
    }

    public function stream_open(string $path, string $mode, int $options): bool
    {
        $resource = $this->native(fn (): mixed => @fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0));

        if ($resource === false) {
            return false;
        }

        $this->resource = $resource;

        return true;
    }

    public function dir_opendir(string $path): bool
    {
        $resource = $this->native(fn (): mixed => @opendir($path));

        if ($resource === false) {
            return false;
        }

        $this->resource = $resource;

        return true;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return match ($method) {
            'stream_read' => fread($this->resource, max(1, (int) $arguments[0])),
            'stream_write' => fwrite($this->resource, (string) $arguments[0]),
            'stream_eof' => feof($this->resource),
            'stream_close' => fclose($this->resource),
            'stream_stat' => fstat($this->resource),
            'dir_readdir' => readdir($this->resource),
            'dir_closedir' => closedir($this->resource) ?? true,
            'url_stat' => $this->native(fn (): mixed => @stat((string) $arguments[0])),
            'unlink' => $this->native(fn (): bool => @unlink((string) $arguments[0])),
            default => false,
        };
    }

    private function native(Closure $callback): mixed
    {
        self::disable();

        try {
            return $callback();
        } finally {
            self::enable();
        }
    }
}
