<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Bootstrapper;
use Pest\Plugins\Tia\Configuration;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\FileState;
use Pest\Plugins\Tia\Storage;
use Pest\Support\Container;
use Pest\TestSuite;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\GitRepo;

afterEach(function (): void {
    Storage::useDirectory(null);
});

it('uses a project-relative configured directory', function (): void {
    $container = new Container;
    $testSuite = new TestSuite(sys_get_temp_dir(), 'tests');
    $container->add(TestSuite::class, $testSuite);

    (new Configuration)->directory('.pest/tia');
    new Bootstrapper($container)->boot();

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

it('keeps the state directory of a project at the repository root', function (): void {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-storage-'.bin2hex(random_bytes(8));

    mkdir($root.'/backend', 0755, true);

    $repo = new GitRepo($root);
    $repo->init('master');
    $repo->addOrigin('git@github.com:pestphp/tia-fixture.git');

    $home = $root.DIRECTORY_SEPARATOR.'.home';
    mkdir($home, 0755, true);

    $original = getenv('HOME');
    putenv('HOME='.$home);

    try {
        $rootDir = Storage::tempDir($root);
        $nestedDir = Storage::tempDir($root.'/backend');
    } finally {
        putenv($original === false ? 'HOME' : 'HOME='.$original);
        new Process(['rm', '-rf', $root])->run();
    }

    $identity = substr(hash('sha256', 'github.com/pestphp/tia-fixture'), 0, 16);

    expect(basename($rootDir))->toEndWith('-'.$identity)
        ->and(basename($nestedDir))->not->toEndWith('-'.$identity);
})->skipOnWindows();
