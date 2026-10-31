<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use Closure;

/**
 * @internal
 */
final class ReadCapture
{
    private const string PROTOCOL = 'file';

    private const int STREAM_OPEN_FOR_INCLUDE = 0x00000080;

    /**
     * @var resource|null
     */
    public mixed $context = null;

    /**
     * @var resource|object
     */
    private mixed $handle;

    private static ?Recorder $activeRecorder = null;

    /**
     * @var class-string|null
     */
    private static ?string $previousWrapper = null;

    public static function start(Recorder $recorder): void
    {
        if (self::$activeRecorder instanceof Recorder) {
            return;
        }

        self::$previousWrapper = self::registeredWrapper();
        self::$activeRecorder = $recorder;

        self::enable();
    }

    public static function stop(): void
    {
        if (! self::$activeRecorder instanceof Recorder) {
            return;
        }

        self::$activeRecorder = null;

        self::disable();

        self::$previousWrapper = null;
    }

    /**
     * @return class-string|null
     */
    private static function registeredWrapper(): ?string
    {
        $handle = @fopen(__FILE__, 'rb');

        if ($handle === false) {
            return null;
        }

        $metadata = stream_get_meta_data($handle);

        fclose($handle);

        $wrapper = $metadata['wrapper_data'] ?? null;

        if ($metadata['wrapper_type'] !== 'user-space' || ! is_object($wrapper) || $wrapper instanceof self) {
            return null;
        }

        return $wrapper::class;
    }

    private static function enable(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, self::class);
    }

    private static function disable(): void
    {
        if (self::$previousWrapper === null) {
            stream_wrapper_restore(self::PROTOCOL);

            return;
        }

        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, self::$previousWrapper);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $handle = $this->withOriginalWrapper(function () use ($path, $mode, $options, &$openedPath) {
            $handle = self::$previousWrapper === null
                ? $this->openNatively($path, $mode, $options)
                : $this->openThroughPreviousWrapper(self::$previousWrapper, $path, $mode, $options, $openedPath);

            if ($handle !== false && $this->isRead($mode, $options)) {
                self::$activeRecorder?->linkRead($path);
            }

            return $handle;
        });

        if ($handle === false) {
            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function dir_closedir(): bool
    {
        closedir($this->resource());

        return true;
    }

    public function dir_opendir(string $path): bool
    {
        $handle = $this->withOriginalWrapper(fn () => opendir($path, $this->context));

        if (! is_resource($handle)) {
            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->resource());
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->resource());

        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return $this->withOriginalWrapper(fn (): bool => mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0, $this->context));
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        return $this->withOriginalWrapper(fn (): bool => rename($pathFrom, $pathTo, $this->context));
    }

    public function rmdir(string $path): bool
    {
        return $this->withOriginalWrapper(fn (): bool => rmdir($path, $this->context));
    }

    /**
     * @return resource|false
     */
    public function stream_cast(int $castAs): mixed
    {
        return $this->forward('stream_cast', fn (mixed $resource): mixed => $resource, $castAs);
    }

    public function stream_close(): void
    {
        $this->forward('stream_close', fn (mixed $resource): bool => fclose($resource));
    }

    public function stream_eof(): bool
    {
        return $this->forward('stream_eof', fn (mixed $resource): bool => feof($resource)) === true;
    }

    public function stream_flush(): bool
    {
        return $this->forward('stream_flush', fn (mixed $resource): bool => fflush($resource)) === true;
    }

    /**
     * @param  int<0, 7>  $operation
     */
    public function stream_lock(int $operation): bool
    {
        return $this->forward('stream_lock', fn (mixed $resource): bool => flock($resource, $operation), $operation) === true;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return $this->withOriginalWrapper(fn (): bool => match ($option) {
            STREAM_META_TOUCH => is_array($value) && is_int($value[0] ?? null) && is_int($value[1] ?? null)
                ? touch($path, $value[0], $value[1])
                : touch($path),
            STREAM_META_OWNER_NAME, STREAM_META_OWNER => (is_string($value) || is_int($value)) && chown($path, $value),
            STREAM_META_GROUP_NAME, STREAM_META_GROUP => (is_string($value) || is_int($value)) && chgrp($path, $value),
            STREAM_META_ACCESS => is_int($value) && chmod($path, $value),
            default => false,
        });
    }

    /**
     * @param  int<1, max>  $count
     */
    public function stream_read(int $count): string|false
    {
        $data = $this->forward('stream_read', fn (mixed $resource): string|false => fread($resource, $count), $count);

        return is_string($data) ? $data : false;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return $this->forward('stream_seek', fn (mixed $resource): bool => fseek($resource, $offset, $whence) === 0, $offset, $whence) === true;
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return $this->forward('stream_set_option', fn (mixed $resource): bool => match ($option) {
            STREAM_OPTION_BLOCKING => stream_set_blocking($resource, (bool) $arg1),
            STREAM_OPTION_READ_TIMEOUT => stream_set_timeout($resource, $arg1, $arg2 ?? 0),
            STREAM_OPTION_WRITE_BUFFER => stream_set_write_buffer($resource, $arg1) === 0,
            STREAM_OPTION_READ_BUFFER => stream_set_read_buffer($resource, $arg1) === 0,
            default => false,
        }, $option, $arg1, $arg2) === true;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        $stat = $this->forward('stream_stat', fn (mixed $resource): array|false => fstat($resource));

        return is_array($stat) ? $stat : false;
    }

    public function stream_tell(): int|false
    {
        $position = $this->forward('stream_tell', fn (mixed $resource): int|false => ftell($resource));

        return is_int($position) ? $position : false;
    }

    /**
     * @param  int<0, max>  $newSize
     */
    public function stream_truncate(int $newSize): bool
    {
        return $this->forward('stream_truncate', fn (mixed $resource): bool => ftruncate($resource, $newSize), $newSize) === true;
    }

    public function stream_write(string $data): int|false
    {
        $written = $this->forward('stream_write', fn (mixed $resource): int|false => fwrite($resource, $data), $data);

        return is_int($written) ? $written : false;
    }

    public function unlink(string $path): bool
    {
        return $this->withOriginalWrapper(fn (): bool => unlink($path, $this->context));
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return $this->withOriginalWrapper(function () use ($path, $flags): array|false {
            if (($flags & STREAM_URL_STAT_QUIET) !== 0 && ! file_exists($path)) {
                return false;
            }

            return ($flags & STREAM_URL_STAT_LINK) !== 0 ? @lstat($path) : @stat($path);
        });
    }

    /**
     * @return resource|false
     */
    private function openNatively(string $path, string $mode, int $options): mixed
    {
        return ($options & STREAM_REPORT_ERRORS) !== 0
            ? fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0, $this->context)
            : @fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0, $this->context);
    }

    /**
     * @param  class-string  $wrapperClass
     */
    private function openThroughPreviousWrapper(string $wrapperClass, string $path, string $mode, int $options, ?string &$openedPath): object|false
    {
        $wrapper = new $wrapperClass;

        if (property_exists($wrapper, 'context')) {
            $wrapper->context = $this->context;
        }

        if (! method_exists($wrapper, 'stream_open') || $wrapper->stream_open($path, $mode, $options, $openedPath) !== true) {
            return false;
        }

        return $wrapper;
    }

    private function isRead(string $mode, int $options): bool
    {
        if (($options & self::STREAM_OPEN_FOR_INCLUDE) !== 0) {
            return false;
        }

        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    /**
     * @return resource
     */
    private function resource(): mixed
    {
        assert(is_resource($this->handle));

        return $this->handle;
    }

    /**
     * @param  Closure(resource): mixed  $native
     */
    private function forward(string $method, Closure $native, mixed ...$arguments): mixed
    {
        if (! is_object($this->handle)) {
            return $native($this->handle);
        }

        if (! method_exists($this->handle, $method)) {
            return false;
        }

        return $this->handle->{$method}(...$arguments);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withOriginalWrapper(Closure $callback): mixed
    {
        self::disable();

        try {
            return $callback();
        } finally {
            self::enable();
        }
    }
}
