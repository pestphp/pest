<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('creates and matches snapshots with long dataset labels', function (): void {
    $project = Project::withoutGit();
    $testFile = 'tests/Unit/SnapshotNamesTest.php';
    $snapshotDirectory = $project->path('tests/.pest/snapshots/Unit/SnapshotNamesTest');

    $project->write($testFile, <<<'FIXTURE'
        <?php

        $prefix = str_repeat('日本', 135);

        test('stores a snapshot', function (string $value): void {
            expect($value)->toMatchSnapshot()
                ->and(strtoupper($value))->toMatchSnapshot(as: 'upper');
        })->with([
            'short' => ['short'],
            $prefix.' one' => ['one'],
            $prefix.' two' => ['two'],
        ]);
        FIXTURE);

    $first = $project->pest($testFile, '--update-snapshots');
    $snapshots = glob($snapshotDirectory.'/*.snap');

    expect($first->exitCode)->toBe(0, $first->describe())
        ->and($snapshots)->toHaveCount(6)
        ->and(array_map(file_get_contents(...), $snapshots))->toEqualCanonicalizing(['short', 'SHORT', 'one', 'ONE', 'two', 'TWO'])
        ->and($snapshotDirectory.'/stores_a_snapshot_with_data_set__dataset__short__.snap')->toBeFile()
        ->and($snapshotDirectory.'/stores_a_snapshot_with_data_set__dataset__short____upper.snap')->toBeFile();

    foreach ($snapshots as $snapshot) {
        $filename = basename($snapshot);

        expect(strlen($filename))->toBeLessThanOrEqual(255)
            ->and(mb_check_encoding($filename, 'UTF-8'))->toBeTrue();
    }

    $second = $project->pestWithEnvironment($project->path(), ['CI' => 'true'], $testFile);

    expect($second->exitCode)->toBe(0, $second->describe())
        ->and($second->tally())->toContain('3 passed');
})->group('integration');
