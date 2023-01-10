<?php

todo('something todo later');

test('something todo later chained')->todo();

test('something todo later chained and with function body', function () {
    expect(true)->toBeFalse();
})->todo();

it('does something within a file with a todo', function () {
    expect(true)->toBeTrue();
});
