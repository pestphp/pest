<?php

it('runs the first test', function () {
    expect(true)->toBeTrue();
});

it('runs the second test', function () {
    expect(true)->toBeTrue();
});

it('runs the third test', function () {
    expect(true)->toBeTrue();
});

test('calls Foo::it runs the third test', function () {
    expect(true)->toBeTrue();
});
