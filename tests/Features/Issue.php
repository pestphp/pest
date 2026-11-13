<?php

declare(strict_types=1);

beforeEach(function (): void {
    expect(true)->toBeTrue();
})->issue(1);

it('may be associated with an issue', function (): void {
    expect(true)->toBeTrue();
})->issue(2);

describe('nested', function (): void {
    it('may be associated with an issue', function (): void {
        expect(true)->toBeTrue();
    })->issue('#3');
})->issue(4)->note('an note between an the issue')->issue([5, 6]);
