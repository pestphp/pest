<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('locally configured tia does not replay when the process is on CI', function (): void {
    $project = Project::make('master');
    $pestFile = $project->path('tests/Pest.php');
    file_put_contents($pestFile, file_get_contents($pestFile)."\npest()->tia()->locally();\n");
    $project->seed('master');

    $onCi = $project->pestWithEnvironment($project->path(), ['CI' => 'true']);

    expect($onCi->exitCode)->toBe(0, $onCi->describe())
        ->and($onCi->output)->not->toContain('TIA mode')
        ->and($onCi->replayed())->toBe(0)
        ->and($onCi->tally())->toContain(Project::TOTAL_TESTS.' passed');

    $local = $project->pest();

    expect($local->exitCode)->toBe(0, $local->describe())
        ->and($local->replayed())->toBe(Project::TOTAL_TESTS, $local->describe());
})->skipOnWindows();
