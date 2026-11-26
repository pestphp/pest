<?php

declare(strict_types=1);

use Pest\Exceptions\GitTimedOut;
use Pest\Support\Git;

it('raises a timed out command instead of answering', function (string $method): void {
    $git = new Git(timeout: 0.2);

    expect(fn (): mixed => $git->{$method}(['-c', 'alias.slow=!sleep 5', 'slow']))
        ->toThrow(GitTimedOut::class, 'Git did not answer within 0.2 seconds: git -c alias.slow=!sleep 5 slow');
})->with(['result', 'raw', 'output', 'succeeds'])->skipOnWindows();
