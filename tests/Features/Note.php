<?php

beforeEach(function (): void {
    $this->note('This is before each runtime note');
})->note('This is before each static note');

it('may have a static note', function (): void {
    expect(true)->toBeTrue();
})->note('This is a note');

it('may have a runtime note', function (): void {
    expect(true)->toBeTrue(true);

    $this->note('This is a runtime note');
});

it('may have static note and runtime note', function (): void {
    expect(true)->toBeTrue(true);

    $this->note('This is a runtime note');
})->note('This is a static note');

describe('nested', function (): void {
    beforeEach(function (): void {
        $this->note('This is before each describe runtime note');
    })->note('This is before each describe static note');

    it('may have static note and runtime note', function (): void {
        expect(true)->toBeTrue(true);

        $this->note('This is a runtime note within describe');
    })->note('This is a static note within describe');

    describe('describe nested within describe', function (): void {
        beforeEach(function (): void {
            $this->note('This is before each nested describe runtime note');
        })->note('This is before each nested describe static note');

        it('may have a static note and runtime note', function (): void {
            expect(true)->toBeTrue(true);

            $this->note('This is a runtime note within a nested describe');
        })->note('This is a static note within a nested describe');
    })->note('This is a nested describe static note');
})->note('This is describe static note');

describe('matching describe names', function (): void {
    beforeEach(function (): void {
        $this->note('This is before each matching describe runtime note');
    })->note('This is before each matching describe static note');

    describe('describe block', function (): void {
        beforeEach(function (): void {
            $this->note('This is before each matching describe runtime note');
        })->note('This is before each matching describe static note');

        it('may have a static note and runtime note', function (): void {
            expect(true)->toBeTrue(true);

            $this->note('This is a runtime note within a matching describe');
        })->note('This is a static note within a matching describe');
    })->note('This is a nested matching static note');

    describe('describe block', function (): void {
        beforeEach(function (): void {
            $this->note('This is before each matching describe runtime note, and should not contain the matching describe notes');
        })->note('This is before each matching describe static note, and should not contain the matching describe notes');

        it('may have a static note and runtime note, that are different than the matching describe block', function (): void {
            expect(true)->toBeTrue(true);

            $this->note('This is a runtime note within a matching describe, and should not contain the matching describe notes');
        })->note('This is a static note within a matching describe, and should not contain the matching describe notes');
    })->note('This is a nested matching static note, and should not contain the matching describe notes');
});

test('multiple notes', function (): void {
    expect(true)->toBeTrue(true);

    $this->note([
        'This is a runtime note',
        'This is another runtime note',
    ]);
});
