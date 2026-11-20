<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\Project;

test('runs with a nested Composer vendor directory', function (string $binary, array $arguments, int $assertions): void {
    $project = Project::withoutGit();

    try {
        $vendor = 'build/dependencies';
        mkdir($project->path('build'));
        rename($project->path('vendor'), $project->path($vendor));

        $project->write($vendor.'/composer/installed.php', <<<'METADATA'
            <?php

            return ['root' => ['install_path' => __DIR__.'/../../..']];
            METADATA);

        $project->write($vendor.'/bin/pest', <<<'PROXY'
            <?php

            $GLOBALS['_composer_bin_dir'] = __DIR__;
            $GLOBALS['_composer_autoload_path'] = __DIR__.'/../autoload.php';

            return include __DIR__.'/../pestphp/pest/bin/pest';
            PROXY);

        $project->write($vendor.'/pestphp/pest/.temp/.gitkeep', '');
        $project->write('phpunit.xml', <<<'CONFIGURATION'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit bootstrap="build/dependencies/autoload.php" cacheDirectory=".phpunit.cache"/>
            CONFIGURATION);

        $test = 'tests/NestedVendorTest.php';
        $project->write($test, <<<'FIXTURE'
            <?php

            test('uses the project root', function (): void {
                expect(Pest\TestSuite::getInstance()->rootPath)->toBe(realpath(__DIR__.'/..'));

                if (($_SERVER['PARATEST'] ?? null) === '1') {
                    expect($_SERVER['PEST_PARALLEL'] ?? null)->toBe('1');
                }
            });
            FIXTURE);

        $process = new Process(
            [PHP_BINARY, $project->path($vendor.'/'.$binary), '--configuration='.$project->path('phpunit.xml'), '--colors=never', $project->path($test), ...$arguments],
            dirname($project->path),
            ['PARATEST' => '0', 'PAO_DISABLE' => '1'],
        );

        expect($process->run())->toBe(0)
            ->and($process->getOutput())->toContain("1 passed ($assertions assertions)");
    } finally {
        Project::destroyAll();
    }
})->with([
    'Composer proxy' => ['bin/pest', [], 1],
    'Composer proxy with workers' => ['bin/pest', ['--parallel', '--processes=2'], 2],
    'direct execution with workers' => ['pestphp/pest/bin/pest', ['--parallel', '--processes=2'], 2],
]);
