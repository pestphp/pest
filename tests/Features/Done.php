<?php

declare(strict_types=1);

it('may have an associated assignee', function (): void {
    expect(true)->toBeTrue();
})->done(assignee: 'nunomaduro');

it('may have an associated issue', function (): void {
    expect(true)->toBeTrue();
})->done(issue: 1);

it('may have an associated PR', function (): void {
    expect(true)->toBeTrue();
})->done(pr: 1);

it('may have an associated note', function (): void {
    expect(true)->toBeTrue();
})->done(note: 'a note');
