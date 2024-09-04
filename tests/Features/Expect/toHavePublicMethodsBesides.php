<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToHavePublicMethodsBesides\UserController;

test('pass', function () {
    expect(UserController::class)->not->toHavePublicMethodsBesides(['publicMethod']);
});

test('failures', function () {
    expect(UserController::class)->not->toHavePublicMethods();
})->throws(ArchExpectationFailedException::class);
