<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Bootstrapper;
use Pest\Plugins\Tia\Configuration;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\FileState;
use Pest\Plugins\Tia\Storage;
use Pest\Support\Container;
use Pest\TestSuite;

afterEach(function (): void {
    Storage::useDirectory(null);
});

it('uses a project-relative configured directory', function (): void {
    $container = new Container;
    $testSuite = new TestSuite(sys_get_temp_dir(), 'tests');
    $container->add(TestSuite::class, $testSuite);

    (new Configuration)->directory('.pest/tia');
    (new Bootstrapper($container))->boot();

    $state = $container->get(State::class);

    if (! $state instanceof FileState) {
        throw new RuntimeException('Expected the TIA state to use file storage.');
    }

    expect(Storage::tempDir('/project'))->toBe('/project'.DIRECTORY_SEPARATOR.'.pest/tia')
        ->and($state->pathFor('graph.json'))->toBe($testSuite->rootPath.DIRECTORY_SEPARATOR.'.pest/tia/graph.json');
});

it('uses an absolute configured directory', function (): void {
    (new Configuration)->directory('/tmp/pest-tia');

    expect(Storage::tempDir('/project'))->toBe('/tmp/pest-tia');
});
