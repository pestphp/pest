<?php

declare(strict_types=1);

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToUseStrictTypes\HasNoStrictType;
use Tests\Fixtures\Arch\ToUseStrictTypes\HasStrictType;
use Tests\Fixtures\Arch\ToUseStrictTypes\HasStrictTypeWithCommentsAbove;

test('pass', function () {
    expect(HasStrictType::class)->toUseStrictTypes()
        ->and(HasStrictTypeWithCommentsAbove::class)->toUseStrictTypes();
});

test('failures', function () {
    expect(HasNoStrictType::class)->toUseStrictTypes();
})->throws(ArchExpectationFailedException::class);
