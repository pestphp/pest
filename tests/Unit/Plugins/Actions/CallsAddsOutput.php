<?php

use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\ObservesExitCode;
use Pest\Plugin\Loader;
use Pest\Plugins\Actions\CallsAddsOutput;

function withExitCodePlugins(array $plugins, Closure $test): void
{
    $loader = new ReflectionClass(Loader::class);
    $instances = $loader->getProperty('instances');
    $loaded = $loader->getProperty('loaded');
    $previousInstances = $instances->getValue();
    $previousLoaded = $loaded->getValue();

    try {
        $instances->setValue(null, $plugins);
        $loaded->setValue(null, true);

        $test();
    } finally {
        $instances->setValue(null, $previousInstances);
        $loaded->setValue(null, $previousLoaded);
    }
}

test('exit code observers run after every output plugin', function (): void {
    $events = new ArrayObject;
    $observer = new readonly class($events) implements ObservesExitCode
    {
        public function __construct(private ArrayObject $events) {}

        public function observeExitCode(int $exitCode): void
        {
            $this->events[] = ['observed', $exitCode];
        }
    };
    $output = new readonly class($events) implements AddsOutput
    {
        public function __construct(private ArrayObject $events) {}

        public function addOutput(int $exitCode): int
        {
            $this->events[] = ['output', $exitCode];

            return $exitCode + 1;
        }
    };

    withExitCodePlugins([$observer, $output, clone $observer, clone $output], function () use ($events): void {
        expect(CallsAddsOutput::execute(0))->toBe(2)
            ->and($events->getArrayCopy())->toBe([
                ['output', 0],
                ['output', 1],
                ['observed', 2],
                ['observed', 2],
            ]);
    });
});

test('exit code observers receive unchanged codes when there are no output plugins', function (int $exitCode): void {
    $observer = new class implements ObservesExitCode
    {
        public ?int $exitCode = null;

        public function observeExitCode(int $exitCode): void
        {
            $this->exitCode = $exitCode;
        }
    };

    withExitCodePlugins([$observer], function () use ($observer, $exitCode): void {
        expect(CallsAddsOutput::execute($exitCode))->toBe($exitCode)
            ->and($observer->exitCode)->toBe($exitCode);
    });
})->with([0, 1, 2, 42]);

test('output plugins work without exit code observers', function (): void {
    $output = new class implements AddsOutput
    {
        public function addOutput(int $exitCode): int
        {
            return 42;
        }
    };

    withExitCodePlugins([$output], function (): void {
        expect(CallsAddsOutput::execute(0))->toBe(42);
    });
});

test('exit code observers are not called when an output plugin throws', function (): void {
    $observer = new class implements ObservesExitCode
    {
        public bool $called = false;

        public function observeExitCode(int $exitCode): void
        {
            $this->called = true;
        }
    };
    $output = new class implements AddsOutput
    {
        public function addOutput(int $exitCode): int
        {
            throw new RuntimeException('Output failed');
        }
    };

    withExitCodePlugins([$observer, $output], function () use ($observer): void {
        expect(fn (): int => CallsAddsOutput::execute(0))->toThrow(RuntimeException::class, 'Output failed')
            ->and($observer->called)->toBeFalse();
    });
});
