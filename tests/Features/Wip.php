<?php

it('may have an associated assignee', function () {
    expect(true)->toBeTrue();
})->wip(assignee: 'nunomaduro');

it('may have an associated issue', function () {
    expect(true)->toBeTrue();
})->wip(issue: 1);

it('may have an associated PR', function () {
    expect(true)->toBeTrue();
})->wip(pr: 1);

it('may have an associated note', function () {
    expect(true)->toBeTrue();
})->wip(note: 'a note');
