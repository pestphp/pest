<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ChangedFiles;
use Tests\Fixtures\Tia\Project;

afterEach(function (): void {
    Project::destroyAll();
});

test('a worktree rename recorded as intent-to-add keeps both paths', function (): void {
    $project = Project::make('master');

    $project->write('app/Stub.php', 'same content');
    $project->git()->commit('add a file that will be moved');

    $project->write('lib/Stub.php', 'same content');
    $project->git()->run(['add', '-N', 'lib/Stub.php']);
    unlink($project->path('app/Stub.php'));

    $changed = new ChangedFiles($project->path())->since(null);

    expect($changed)->toContain('app/Stub.php')
        ->and($changed)->toContain('lib/Stub.php');
});
