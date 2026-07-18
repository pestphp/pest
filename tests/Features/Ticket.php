<?php

beforeEach(function (): void {
    expect(true)->toBeTrue();
})->ticket(1);

it('may be associated with an ticket', function (): void {
    expect(true)->toBeTrue();
})->ticket(2);

describe('nested', function (): void {
    it('may be associated with an ticket', function (): void {
        expect(true)->toBeTrue();
    })->ticket(3);
})->ticket(4)->note('an note between an the ticket')->ticket([5, 6]);
