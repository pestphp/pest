<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToHavePublicMethodsBesides\UserController;

test('pass', function (): void {
    expect(UserController::class)->not->toHavePrivateMethodsBesides(['privateMethod']);
});

test('failures', function (): void {
    expect(UserController::class)->not->toHavePrivateMethods();
})->throws(ArchExpectationFailedException::class);
