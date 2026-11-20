<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('preserves an existing .pest.xml file', function (array $arguments, bool $hasDefaultConfiguration): void {
    $project = Project::withoutGit();
    $configuration = $project->path('.pest.xml');
    $contents = $arguments === [] ? 'An existing project file.' : (string) file_get_contents($project->path('phpunit.xml'));
    $project->write('.pest.xml', $contents);

    if (! $hasDefaultConfiguration) {
        unlink($project->path('phpunit.xml'));
    }

    $result = $project->pest('tests/Unit/GreeterTest.php', '--cache-directory', $project->path('.phpunit.cache'), ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain('2 passed')
        ->and(glob($project->path('.pest*.xml')))->toHaveCount(1)
        ->and($configuration)->toBeFile()
        ->and(file_get_contents($configuration))->toBe($contents);
})->with([
    'explicit configuration' => [['--configuration=.pest.xml'], false],
    'discovered configuration' => [[], true],
    'generated configuration' => [[], false],
])->group('integration');

test('removes the generated configuration when it finishes', function (array $arguments): void {
    $project = Project::withoutGit();
    unlink($project->path('phpunit.xml'));

    $result = $project->pest('tests/Unit/GreeterTest.php', '--cache-directory', $project->path('.phpunit.cache'), ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain('2 passed')
        ->and(glob($project->path('.pest*.xml')))->toBe([]);
})->with(Project::SEQUENTIAL_AND_PARALLEL)->group('integration');

test('preserves a replacement for the generated configuration', function (): void {
    $project = Project::withoutGit();
    unlink($project->path('phpunit.xml'));
    $project->write('replacement.xml', 'A replacement project file.');
    $project->write('tests/Unit/ReplaceConfigurationTest.php', <<<'PHP'
        <?php

        test('replaces the configuration', function (): void {
            expect(rename('replacement.xml', '.pest.xml'))->toBeTrue();
        });
        PHP);

    $result = $project->pest('tests/Unit/ReplaceConfigurationTest.php', '--cache-directory', $project->path('.phpunit.cache'));

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($project->path('.pest.xml'))->toBeFile()
        ->and(file_get_contents($project->path('.pest.xml')))->toBe('A replacement project file.');
})->group('integration');
