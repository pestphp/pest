<?php

declare(strict_types=1);

it('may have an associated assignee', function (): void {
    expect(true)->toBeTrue();
})->wip(assignee: 'nunomaduro');

it('may have an associated issue', function (): void {
    expect(true)->toBeTrue();
})->wip(issue: 1);

it('may have an associated PR', function (): void {
    expect(true)->toBeTrue();
})->wip(pr: 1);

it('may have an associated note', function (): void {
    expect(true)->toBeTrue();
})->wip(note: 'a note');
