<?php

it('may have an associated assignee', function () {
    expect(true)->toBeTrue();
})->done(assignee: 'nunomaduro');

it('may have an associated issue', function () {
    expect(true)->toBeTrue();
})->done(issue: 1);

it('may have an associated PR', function () {
    expect(true)->toBeTrue();
})->done(pr: 1);

it('may have an associated note', function () {
    expect(true)->toBeTrue();
})->done(note: 'a note');
