<?php

declare(strict_types=1);

use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('dirty selects paths with spaces and Unicode', function (string $directory): void {
    $project = Project::make();
    $root = $directory === '' ? $project->path() : $project->nested($directory);
    $testDirectory = $directory === '' ? 'tests/Unit' : $directory.'/tests/Unit';
    $project->git()->run(['config', 'core.quotePath', 'true']);

    foreach (['RegularTest', 'Before Rename Test', 'UnchangedTest', 'DeletedTest'] as $file) {
        $project->write($testDirectory.'/'.$file.'.php', "<?php\n\nit('runs $file')->assertTrue(true);\n");
    }

    $project->git()->commit('add dirty test fixtures');
    $project->git()->run(['mv', $testDirectory.'/Before Rename Test.php', $testDirectory.'/After Rename Test.php']);
    $project->write($testDirectory.'/RegularTest.php', "<?php\n\nit('runs modified test')->assertTrue(true);\n");
    $project->write($testDirectory.'/Café Test.php', "<?php\n\nit('runs new test')->assertTrue(true);\n");
    unlink($project->path($testDirectory.'/DeletedTest.php'));

    $result = $project->pestIn($root, '--dirty');

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('3 passed (3 assertions)', 'runs modified test', 'runs Before Rename Test', 'runs new test')
        ->not->toContain('runs UnchangedTest', 'runs DeletedTest');
})->with([
    'repository root' => [''],
    'project subdirectory' => ['backend'],
    'subdirectory with leading space' => [' backend'],
])->skipOnWindows();
