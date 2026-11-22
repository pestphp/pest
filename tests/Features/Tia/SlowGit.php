<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

function fakeGitPath(Project $project, string $case): string
{
    $git = (new ExecutableFinder)->find('git');

    assert(is_string($git));

    $directory = $project->path('.fake-git');
    $realGit = escapeshellarg($git);

    mkdir($directory);
    file_put_contents($directory.'/git', <<<SH
        #!/bin/sh
        case "\$1 \$2" in
            {$case}
        esac
        exec {$realGit} "\$@"
        SH);
    chmod($directory.'/git', 0755);

    return $directory.PATH_SEPARATOR.getenv('PATH');
}

test('a slow git while naming the branch records nothing without --tia', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $path = fakeGitPath($project, '"symbolic-ref "*) exec sleep 6 ;;');
    $result = $project->pestWithEnvironment($project->path(), ['PATH' => $path]);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->output)->not->toContain('Git did not answer')
        ->and($delta->baselineUntouched('master'))->toBeTrue($delta->summary());
})->skipOnWindows();

test('a slow branch listing after the suite keeps the run results without --tia', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $path = fakeGitPath($project, '"for-each-ref "*) exec sleep 6 ;;');
    $result = $project->pestWithEnvironment($project->path(), ['PATH' => $path]);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($result->output)->not->toContain('Git did not answer')
        ->and($project->branchKeys())->toBe(['master'])
        ->and($delta->writtenCount())->toBe(Project::TOTAL_TESTS, $delta->summary());
})->skipOnWindows();

test('a run without --tia records nothing when git cannot name the current branch', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $path = fakeGitPath($project, '"rev-parse --abbrev-ref") exit 128 ;;');
    $result = $project->pestWithEnvironment($project->path(), ['PATH' => $path]);
    $delta = $project->delta();

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->tally())->toContain(Project::TOTAL_TESTS.' passed')
        ->and($project->branchKeys())->toBe(['master'])
        ->and($delta->baselineUntouched('master'))->toBeTrue($delta->summary());
})->skipOnWindows();

test('a slow git stops a --tia run with the timeout', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $path = fakeGitPath($project, '"symbolic-ref "*) exec sleep 6 ;;');
    $result = $project->pestWithEnvironment($project->path(), ['PATH' => $path], '--tia');

    expect($result->exitCode)->toBe(1, $result->describe())
        ->and($result->output)->toContain('Git did not answer within 5 seconds: git symbolic-ref')
        ->and($result->output)->not->toContain('default branch');
})->skipOnWindows();
