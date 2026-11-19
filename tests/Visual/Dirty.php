<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\GitRepo;
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

test('dirty ignores a test removed after staging a modification', function (array $arguments): void {
    $project = Project::make();
    $testFile = 'tests/Unit/GreeterTest.php';
    $project->write($testFile, "<?php\n\nit('runs staged test')->assertTrue(true);\n");
    $project->git()->run(['add', '--', $testFile]);
    unlink($project->path($testFile));

    $result = $project->pest('--dirty', '--fail-on-empty-test-suite', '--cache-directory', $project->path('.home/cache'), ...$arguments);

    expect($result->exitCode)->toBe(0, $result->describe())
        ->and($result->output)->toContain('No "dirty" tests found.');
})->with(Project::SEQUENTIAL_AND_PARALLEL)->skipOnWindows();

test('dirty selects an unresolved test only while its file exists', function (): void {
    $project = Project::make();
    $testFile = 'tests/Unit/GreeterTest.php';
    $project->git()->switchTo('modified', true);
    $project->write($testFile, "<?php\n\nit('runs the unresolved test')->assertTrue(true);\n");
    $project->git()->commit('modify the test');
    $project->git()->switchTo('master');
    unlink($project->path($testFile));
    $project->git()->commit('remove the test');

    $merge = new Process(['git', 'merge', '--no-edit', 'modified'], $project->path(), GitRepo::ENV);
    expect($merge->run())->toBe(1);

    $present = $project->pest('--dirty', '--fail-on-empty-test-suite');

    expect($present->exitCode)->toBe(0, $present->describe())
        ->and($present->output)->toContain('1 passed (1 assertions)', 'runs the unresolved test');

    unlink($project->path($testFile));
    $missing = $project->pest('--dirty', '--fail-on-empty-test-suite');

    expect($missing->exitCode)->toBe(0, $missing->describe())
        ->and($missing->output)->toContain('No "dirty" tests found.');
})->skipOnWindows();
