<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('reports a missing snapshot that cannot be written', function (): void {
    $project = Project::withoutGit();
    $testFile = 'tests/Unit/BlockedSnapshotTest.php';
    $directory = 'tests/.pest/snapshots/Unit/BlockedSnapshotTest';

    $project->write($directory, 'An existing file.');
    $project->write($testFile, <<<'PHP'
        <?php

        test('stores a snapshot', function (): void {
            expect('content')->toMatchSnapshot();
        });
        PHP);

    $run = $project->pest($testFile, '--update-snapshots');

    expect($run->exitCode)->toBe(2, $run->describe())
        ->and(str_replace('\\', '/', $run->output))->toContain("Unable to write snapshot at [$directory/stores_a_snapshot.snap].")
        ->not->toContain('Snapshot created at')
        ->and(file_get_contents($project->path($directory)))->toBe('An existing file.');
})->group('integration');
