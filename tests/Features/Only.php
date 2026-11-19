<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('limits focus to the invocation that discovers it', function (array $arguments): void {
    $project = Project::withoutGit();
    $testFile = 'tests/Unit/FocusTest.php';
    $project->write('vendor/pestphp/pest/.temp/.gitkeep', '');
    $project->write($testFile, <<<'FIXTURE'
        <?php

        use Symfony\Component\Process\Process;

        test('runs with a named focus group in CI', function (): void {
            expect(true)->toBeTrue();
        })->only('named-focus');

        test('runs an independent Pest invocation', function (): void {
            foreach ([[], ['--parallel', '--processes=2']] as $arguments) {
                $process = new Process(
                    [PHP_BINARY, 'vendor/pestphp/pest/bin/pest', 'tests/Unit/GreeterTest.php', '--cache-directory', '.home/nested', ...$arguments],
                    env: ['PARATEST' => '0'],
                );
                $process->mustRun();

                expect($process->getOutput())->toContain('2 passed');
            }
        })->only();

        test('runs without focus in CI', function (): void {
            expect(true)->toBeTrue();
        });
        FIXTURE);

    $focused = $project->pest($testFile, '--cache-directory', $project->path('.home/focused'), ...$arguments);

    expect($focused->exitCode)->toBe(0, $focused->describe())
        ->and($focused->tally())->toContain('1 passed (2 assertions)');

    $ci = $project->pest($testFile, '--ci', '--cache-directory', $project->path('.home/ci'), ...$arguments);

    expect($ci->exitCode)->toBe(0, $ci->describe())
        ->and($ci->tally())->toContain('3 passed');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->group('integration');
