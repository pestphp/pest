<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToHavePublicMethodsBesides\UserController;

test('pass', function (): void {
    expect(UserController::class)->not->toHavePublicMethodsBesides(['publicMethod']);
});

test('failures', function (): void {
    expect(UserController::class)->not->toHavePublicMethods();
})->throws(ArchExpectationFailedException::class);
