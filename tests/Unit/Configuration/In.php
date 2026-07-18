<?php

declare(strict_types=1);

use Pest\PendingCalls\UsesCall;

it('proxies to uses call', function (): void {
    $in = pest()->in();

    expect($in)->toBeInstanceOf(UsesCall::class);
});
