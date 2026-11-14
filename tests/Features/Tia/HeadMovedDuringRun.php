<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a commit that lands during a run is not stamped as the recorded baseline', function (array $arguments): void {
    $project = Project::make('master');
    $project->seed('master');

    $project->write('tests/Unit/GreeterTest.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        use Fixture\App\Greeter;

        test('greets a person', function (): void {
            $root = dirname(__DIR__, 2);
            $marker = $root.'/commit-during-run';

            if (is_file($marker)) {
                unlink($marker);

                $calculator = $root.'/app/Calculator.php';
                file_put_contents($calculator, str_replace('return $a + $b;', 'return $a + $b + 1000;', (string) file_get_contents($calculator)));

                exec('git -C '.escapeshellarg($root).' commit --quiet --all --message "break add() during the run"', result_code: $exitCode);

                expect($exitCode)->toBe(0);
            }

            expect((new Greeter)->greet('Nuno'))->toBe('Hello, Nuno!');
        });

        test('greets the world', function (): void {
            expect((new Greeter)->greet('world'))->toBe('Hello, world!');
        });
        PHP);
    $project->git()->commit('a greeter test that commits while the run is in flight');
    $project->write('commit-during-run', '');

    $during = $project->pest('--tia', ...$arguments);

    expect($during->exitCode)->toBe(0, $during->describe())
        ->and($project->path('commit-during-run'))->not->toBeFile()
        ->and($during->output)->toContain('HEAD moved during the run');

    $after = $project->pest('--tia', ...$arguments);

    expect($after->exitCode)->toBe(1, $after->describe())
        ->and($after->tally())->toContain('failed')
        ->and($after->replayed())->toBeLessThan(Project::TOTAL_TESTS, $after->describe());
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();
