<?php

beforeEach(function () {
    $this->note('This is before each runtime note');
})->note('This is before each static note');

it('may have a static note', function () {
    expect(true)->toBeTrue();
})->note('This is a note');

it('may have a runtime note', function () {
    expect(true)->toBeTrue(true);

    $this->note('This is a runtime note');
});

it('may have static note and runtime note', function () {
    expect(true)->toBeTrue(true);

    $this->note('This is a runtime note');
})->note('This is a static note');

describe('nested', function () {
    it('may have static note and runtime note', function () {
        expect(true)->toBeTrue(true);

        $this->note('This is a runtime note within describe');
    })->note('This is a static note within describe');
})->note('This is describe static note');

test('multiple notes', function () {
    $this->note([
        'This is a runtime note',
        'This is another runtime note',
    ]);
});
