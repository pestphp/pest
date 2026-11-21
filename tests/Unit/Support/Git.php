<?php

declare(strict_types=1);

use Pest\Support\Git;

it('reports a timed out command as a failure', function (): void {
    $git = new Git(timeout: 0.2);

    $result = $git->result(['-c', 'alias.slow=!sleep 5', 'slow']);

    expect($result)->toBe([
        'exitCode' => Git::TIMED_OUT_EXIT_CODE,
        'output' => '',
        'errorOutput' => 'git -c alias.slow=!sleep 5 slow timed out after 0.2 seconds.',
    ])
        ->and($git->raw(['-c', 'alias.slow=!sleep 5', 'slow']))->toBeNull()
        ->and($git->succeeds(['-c', 'alias.slow=!sleep 5', 'slow']))->toBeFalse();
})->skipOnWindows();
