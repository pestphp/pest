<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ReadCapture;
use Pest\Plugins\Tia\Recorder;
use Tests\Fixtures\Tia\LoggingFileWrapper;

/**
 * @return array<int, string>
 */
function tiaReadCaptureLinks(Closure $callback): array
{
    $recorder = new Recorder;
    $recorder->activateLinkTracking();

    $recorder->beginTest('Some\Missing\TestClass', 'it reads', '/project/tests/Feature/ReadsTest.php');

    try {
        $callback();
    } finally {
        $recorder->endTest();
    }

    return $recorder->perTestFiles()['/project/tests/Feature/ReadsTest.php'] ?? [];
}

function tiaReadCaptureWrapperType(): string
{
    $handle = fopen(__FILE__, 'rb');

    if ($handle === false) {
        throw new RuntimeException(sprintf('Unable to open [%s].', __FILE__));
    }

    try {
        return (string) stream_get_meta_data($handle)['wrapper_type'];
    } finally {
        fclose($handle);
    }
}

function tiaReadCaptureWrapperClass(): ?string
{
    $handle = fopen(__FILE__, 'rb');

    if ($handle === false) {
        throw new RuntimeException(sprintf('Unable to open [%s].', __FILE__));
    }

    try {
        $wrapper = stream_get_meta_data($handle)['wrapper_data'] ?? null;

        return is_object($wrapper) ? $wrapper::class : null;
    } finally {
        fclose($handle);
    }
}

describe('reads', function (): void {
    it('links a project file the test reads', function (Closure $read): void {
        $source = (string) realpath(dirname(__DIR__, 4).'/src/Plugins/Tia/ReadCapture.php');

        expect(tiaReadCaptureLinks(fn () => $read($source)))->toBe([$source]);
    })->with([
        'file_get_contents' => fn (string $path): string|false => file_get_contents($path),
        'file' => fn (string $path): array|false => file($path),
        'fopen' => fn (string $path): bool => fclose(fopen($path, 'rb')),
        'SplFileObject' => fn (string $path): string|false => new SplFileObject($path)->fgets(),
    ]);

    it('does not link a file the test only writes', function (): void {
        $path = __DIR__.'/read-capture-'.bin2hex(random_bytes(8)).'.txt';

        try {
            expect(tiaReadCaptureLinks(fn (): int|false => file_put_contents($path, 'written')))->toBeEmpty();
        } finally {
            @unlink($path);
        }
    });

    it('does not link a file the test includes', function (): void {
        $path = __DIR__.'/read-capture-'.bin2hex(random_bytes(8)).'.php';
        file_put_contents($path, "<?php\n\nreturn 1;\n");

        try {
            expect(tiaReadCaptureLinks(fn (): mixed => require $path))->toBeEmpty();
        } finally {
            @unlink($path);
        }
    });

    it('does not link a file outside the project', function (): void {
        $path = (string) tempnam(sys_get_temp_dir(), 'pest_');
        file_put_contents($path, 'outside');

        try {
            expect(tiaReadCaptureLinks(fn (): string|false => file_get_contents($path)))->toBeEmpty();
        } finally {
            @unlink($path);
        }
    });

    it('does not link a directory the test lists', function (): void {
        expect(tiaReadCaptureLinks(fn (): array|false => scandir(__DIR__)))->toBeEmpty();
    });

    it('records nothing while inactive', function (): void {
        $recorder = new Recorder;

        $recorder->beginTest('Some\Missing\TestClass', 'it reads', '/project/tests/Feature/ReadsTest.php');
        $wrapperType = tiaReadCaptureWrapperType();
        $recorder->endTest();

        expect($wrapperType)->toBe('plainfile')
            ->and($recorder->perTestFiles())->toBeEmpty();
    });
});

describe('the file wrapper', function (): void {
    it('is replaced while a test records and restored after it', function (): void {
        $during = '';

        tiaReadCaptureLinks(function () use (&$during): void {
            $during = tiaReadCaptureWrapperType();
        });

        expect($during)->toBe('user-space')
            ->and(tiaReadCaptureWrapperType())->toBe('plainfile');
    });

    it('is restored when the recorder resets', function (): void {
        $recorder = new Recorder;
        $recorder->activateLinkTracking();

        $recorder->beginTest('Some\Missing\TestClass', 'it reads', '/project/tests/Feature/ReadsTest.php');
        $recorder->reset();

        expect(tiaReadCaptureWrapperType())->toBe('plainfile');
    });

    it('passes filesystem operations through while a test records', function (): void {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-read-capture-'.bin2hex(random_bytes(8));
        $results = [];

        tiaReadCaptureLinks(function () use ($directory, &$results): void {
            $results['mkdir'] = mkdir($directory.'/nested', 0755, true);
            $results['write'] = file_put_contents($directory.'/nested/a.txt', 'abc');
            $results['rename'] = rename($directory.'/nested/a.txt', $directory.'/nested/b.txt');
            $results['touch'] = touch($directory.'/nested/b.txt', 1_000_000_000, 1_000_000_000);
            $results['mtime'] = filemtime($directory.'/nested/b.txt');
            $results['chmod'] = chmod($directory.'/nested/b.txt', 0644);
            $results['exists'] = file_exists($directory.'/nested/b.txt');
            $results['scandir'] = array_values(array_diff((array) scandir($directory.'/nested'), ['.', '..']));

            $handle = fopen($directory.'/nested/b.txt', 'r+b');
            assert(is_resource($handle));
            $results['lock'] = flock($handle, LOCK_EX);
            $results['seek'] = fseek($handle, 1);
            $results['read'] = fread($handle, 2);
            $results['truncate'] = ftruncate($handle, 1);
            $results['size'] = fstat($handle)['size'] ?? null;
            $results['unlock'] = flock($handle, LOCK_UN);
            fclose($handle);

            $results['unlink'] = unlink($directory.'/nested/b.txt');
            $results['rmdir'] = rmdir($directory.'/nested') && rmdir($directory);
        });

        expect($results)->toBe([
            'mkdir' => true,
            'write' => 3,
            'rename' => true,
            'touch' => true,
            'mtime' => 1_000_000_000,
            'chmod' => true,
            'exists' => true,
            'scandir' => ['b.txt'],
            'lock' => true,
            'seek' => 0,
            'read' => 'bc',
            'truncate' => true,
            'size' => 1,
            'unlock' => true,
            'unlink' => true,
            'rmdir' => true,
        ]);
    });
});

describe('a wrapper already registered for file://', function (): void {
    beforeEach(function (): void {
        LoggingFileWrapper::enable();
    });

    afterEach(function (): void {
        LoggingFileWrapper::disable();
    });

    it('opens reads through it and still links them', function (): void {
        $source = (string) realpath(dirname(__DIR__, 4).'/src/Plugins/Tia/ReadCapture.php');

        expect(tiaReadCaptureLinks(fn (): string|false => file_get_contents($source)))->toBe([$source])
            ->and(array_column(LoggingFileWrapper::$opened, 'path'))->toContain($source);
    });

    it('keeps the include flag for the files a test includes', function (): void {
        $path = __DIR__.'/read-capture-'.bin2hex(random_bytes(8)).'.php';
        file_put_contents($path, "<?php\n\nreturn 1;\n");

        try {
            $links = tiaReadCaptureLinks(fn (): mixed => require $path);
        } finally {
            @unlink($path);
        }

        $includes = array_filter(
            LoggingFileWrapper::$opened,
            fn (array $open): bool => $open['including'] && basename($open['path']) === basename($path),
        );

        expect($links)->toBeEmpty()
            ->and($includes)->not->toBeEmpty();
    });

    it('is registered again after the test', function (): void {
        $during = null;

        tiaReadCaptureLinks(function () use (&$during): void {
            $during = tiaReadCaptureWrapperClass();
        });

        expect($during)->toBe(ReadCapture::class)
            ->and(tiaReadCaptureWrapperClass())->toBe(LoggingFileWrapper::class);
    });
});
