<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToHavePublicMethodsBesides\UserController;

test('pass', function () {
    expect(UserController::class)->not->toHaveProtectedMethodsBesides(['protectedMethod']);
});

test('failures', function () {
    expect(UserController::class)->not->toHaveProtectedMethods();
})->throws(ArchExpectationFailedException::class);
