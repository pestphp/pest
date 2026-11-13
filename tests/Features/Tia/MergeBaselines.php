<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ChangedFiles;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a replacement CI merge reuses its baseline and selects changed tests on either parent', function (array $arguments, string $changedBranch): void {
    $project = Project::make('master');
    $git = $project->git();
    $git->switchTo('feature', new: true);
    $git->commit('feature');
    $git->run(['checkout', '--detach', 'master']);
    $git->run(['merge', '--no-ff', '--no-edit', 'feature']);
    $project->seed('123/merge');

    $git->switchTo($changedBranch);
    $project->write('tests/Unit/CalculatorTest.php', str_replace(
        '->add(1, 2)',
        '->add(2, 1)',
        file_get_contents($project->path('tests/Unit/CalculatorTest.php')) ?: throw new RuntimeException('Missing calculator test fixture.'),
    ));
    $git->commit('change calculator');
    $git->run(['checkout', '--detach', 'master']);
    $git->run(['merge', '--no-ff', '--no-edit', 'feature']);

    $result = $project->pestWithEnvironment($project->path(), [
        'GITHUB_REF_NAME' => '123/merge',
    ], '--tia', ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->not->toContain('no longer reachable')
        ->and($result->replayed())->toBe(4, $result->describe())
        ->and($result->tally())->toContain('6 passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->with(['feature', 'master'])->skipOnWindows();

test('a merge baseline is rejected when one of its parents is absent from the current history', function (): void {
    $project = Project::make('master');
    $git = $project->git();
    $git->switchTo('feature', new: true);
    $git->commit('feature');
    $git->run(['checkout', '--detach', 'master']);
    $git->run(['merge', '--no-ff', '--no-edit', 'feature']);
    $sha = $git->sha();
    $git->switchTo('master');

    expect(new ChangedFiles($project->path())->since($sha))->toBeNull();
})->skipOnWindows();
