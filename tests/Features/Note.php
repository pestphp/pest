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
    beforeEach(function () {
        $this->note('This is before each describe runtime note');
    })->note('This is before each describe static note');

    it('may have static note and runtime note', function () {
        expect(true)->toBeTrue(true);

        $this->note('This is a runtime note within describe');
    })->note('This is a static note within describe');

    describe('describe nested within describe', function () {
        beforeEach(function () {
            $this->note('This is before each nested describe runtime note');
        })->note('This is before each nested describe static note');

        it('may have a static note and runtime note', function () {
            expect(true)->toBeTrue(true);

            $this->note('This is a runtime note within a nested describe');
        })->note('This is a static note within a nested describe');
    })->note('This is a nested describe static note');
})->note('This is describe static note');

test('multiple notes', function () {
    expect(true)->toBeTrue(true);

    $this->note([
        'This is a runtime note',
        'This is another runtime note',
    ]);
});
