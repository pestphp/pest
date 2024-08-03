<?php

todo('something todo later');

test('something todo later chained')->todo();

test('something todo later chained and with function body', function () {
    expect(true)->toBeFalse();
})->todo();

it('does something within a file with a todo', function () {
    expect(true)->toBeTrue();
});

it('may have an associated assignee', function () {
    expect(true)->toBeTrue();
})->todo(assignee: 'nunomaduro');

it('may have an associated issue', function () {
    expect(true)->toBeTrue();
})->todo(issue: 1);

it('may have an associated PR', function () {
    expect(true)->toBeTrue();
})->todo(pr: 1);

it('may have an associated note', function () {
    expect(true)->toBeTrue();
})->todo(note: 'a note');
