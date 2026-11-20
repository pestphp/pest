<?php

use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\Project;

test('visual snapshot of help command output', function (): void {
    $output = function (): ?string {
        $process = (new Process(['php', 'bin/pest', '--version'], null, ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1']));

        $process->run();

        return removeAnsiEscapeSequences($process->getOutput());
    };

    expect($output())->toMatchSnapshot();
})->skipOnWindows()->skip(! getenv('REBUILD_SNAPSHOTS') && getenv('EXCLUDE'));

test('prints information when the tests directory is missing', function (string $option, string $expected): void {
    $project = Project::withoutGit();

    try {
        rename($project->path('tests'), $project->path('fixtures'));
        unlink($project->path('phpunit.xml'));

        $result = $project->pest($option);

        expect($result->exitCode)->toBe(0, $result->describe())
            ->and($result->output)->toContain($expected);
    } finally {
        Project::destroyAll();
    }
})->with([
    'help' => ['--help', '--init'],
    'version' => ['--version', 'Pest Testing Framework'],
]);
