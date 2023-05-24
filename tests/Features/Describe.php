<?php

beforeEach(fn () => $this->count = 1);

test('before each', function () {
    expect($this->count)->toBe(1);
});

describe('describable', function () {
    beforeEach(function () {
        $this->count++;
    });

    test('basic', function () {
        expect(true)->toBeTrue();
    });

    test('before each', function () {
        expect($this->count)->toBe(2);
    });

    afterEach(function () {
        expect($this->count)->toBe(2);
    });
});

describe('another describable', function () {
    beforeEach(function () {
        $this->count++;
    });

    test('basic', function () {
        expect(true)->toBeTrue();
    });

    test('before each', function () {
        expect($this->count)->toBe(2);
    });

    afterEach(function () {
        expect($this->count)->toBe(2);
    });
});

test('should not fail')->todo()->shouldNotRun();

test('previous describable before each does not get applied here', function () {
    expect($this->count)->toBe(1);
});

afterEach(fn () => expect($this->count)->toBe(is_null($this->__describeDescription) ? 1 : 2));

describe('todo', function () {
    beforeEach()->todo();

    test('should not fail')->shouldNotRun();
});

describe('todo after describe', function () {
    test('should not fail')->shouldNotRun();
})->todo();
