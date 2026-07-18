<?php

declare(strict_types=1);

use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Tests\Fixtures\Arch\ToUseStrictTypes\HasNoStrictType;
use Tests\Fixtures\Arch\ToUseStrictTypes\HasStrictType;
use Tests\Fixtures\Arch\ToUseStrictTypes\HasStrictTypeWithCommentsAbove;

test('pass', function (): void {
    expect(HasStrictType::class)->toUseStrictTypes()
        ->and(HasStrictTypeWithCommentsAbove::class)->toUseStrictTypes();
});

test('failures', function (): void {
    expect(HasNoStrictType::class)->toUseStrictTypes();
})->throws(ArchExpectationFailedException::class);
