<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('narrows to the affected tests on a new branch', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $project->write('app/Calculator.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Fixture\App;

        final class Calculator
        {
            public function add(int $a, int $b): int
            {
                return $a + $b;
            }

            public function subtract(int $a, int $b): int
            {
                return $a - $b;
            }

            public function multiply(int $a, int $b): int
            {
                return $a * $b;
            }
        }
        PHP);

    $result = $project->pest('--tia');

    expect($result->affected())->toBe(4, $result->describe())
        ->and($result->replayed())->toBe(2, $result->describe())
        ->and($result->exitCode)->toBe(0, $result->describe());
})->skipOnWindows();

test('filtered mode reads the fallback too', function (): void {
    $project = Project::make('master');

    $project->seed('master', failing: ['adds two numbers']);

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia', '--filtered');

    expect($result->output)->toContain('from 1 previously unsuccessful test')
        ->and($result->output)->not->toContain('No affected tests found')
        ->and($result->tally())->toContain('2 passed');
})->skipOnWindows();

test('filtered mode finds nothing to do on a clean green feature branch', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia', '--filtered');
    $delta = $project->delta();

    expect($result->output)->toContain('No affected tests found')
        ->and($result->exitCode)->toBe(0, $result->describe())
        ->and($delta->isHardSuppressed())->toBeTrue($delta->summary());
})->skipOnWindows();

test('a detached HEAD replays without minting a branch key', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->detach();

    $result = $project->pest('--tia');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe())
        ->and($project->branchKeys())->toBe(['master']);
})->skipOnWindows();

test('the branch that ran gets its own key and the default branch keeps its baseline', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);
    $project->pest('--tia');

    $delta = $project->delta();

    expect($project->branchKeys())->toBe(['master', 'feature-x'])
        ->and($delta->baselineUntouched('master'))->toBeTrue($delta->summary())
        ->and($delta->writtenCount())->toBe(0, $delta->summary());
})->skipOnWindows();

test('the fallback reaches parallel workers', function (): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->git()->switchTo('feature-x', new: true);

    $result = $project->pest('--tia', '--parallel', '--processes=2');

    expect($result->replayed())->toBe(Project::TOTAL_TESTS, $result->describe())
        ->and($result->uncached())->toBe(0, $result->describe());
})->skipOnWindows();
