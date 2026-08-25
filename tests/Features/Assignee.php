<?php

declare(strict_types=1);

beforeEach(function (): void {
    expect(true)->toBeTrue();
})->assignee('nunomaduro');

it('may be associated with an assignee', function (): void {
    expect(true)->toBeTrue();
})->assignee('taylorotwell');

describe('nested', function (): void {
    it('may be associated with an assignee', function (): void {
        expect(true)->toBeTrue();
    })->assignee('taylorotwell');
})->assignee('nunomaduro')->note('an note between an the assignee')->assignee(['jamesbrooks', 'joedixon']);
