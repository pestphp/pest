<?php

beforeEach(fn () => $this->count = 1);

test('before each', function () {
    expect($this->count)->toBe(1);
});

describe('hooks', function () {
    beforeEach(function () {
        $this->count++;
    });

    test('value', function () {
        expect($this->count)->toBe(2);
        $this->count++;
    });

    afterEach(function () {
        expect($this->count)->toBe(3);
    });
});

describe('hooks in different orders', function () {
    beforeEach(function () {
        $this->count++;
    });

    test('value', function () {
        expect($this->count)->toBe(3);
        $this->count++;
    });

    afterEach(function () {
        expect($this->count)->toBe(4);
    });

    beforeEach(function () {
        $this->count++;
    });
});

test('todo')->todo()->shouldNotRun();

test('previous describable before each does not get applied here', function () {
    expect($this->count)->toBe(1);
});

describe('todo on hook', function () {
    beforeEach()->todo();

    test('should not fail')->shouldNotRun();
    test('should run')->expect(true)->toBeTrue();
});

describe('todo on describe', function () {
    test('should not fail')->shouldNotRun();

    test('should run')->expect(true)->toBeTrue();
})->todo();

test('should run')->expect(true)->toBeTrue();

test('with', fn ($foo) => expect($foo)->toBe(1))->with([1]);

describe('with on hook', function () {
    beforeEach()->with([2]);

    test('value', function ($foo) {
        expect($foo)->toBe(2);
    });
});

describe('with on describe', function () {
    test('value', function ($foo) {
        expect($foo)->toBe(3);
    });
})->with([3]);

describe('depends on describe', function () {
    test('foo', function () {
        expect('foo')->toBe('foo');
    });

    test('bar', function () {
        expect('bar')->toBe('bar');
    })->depends('foo');
});

describe('depends on describe using with', function () {
    test('foo', function ($foo) {
        expect($foo)->toBe(3);
    });

    test('bar', function ($foo) {
        expect($foo + $foo)->toBe(6);
    })->depends('foo');
})->with([3]);

describe('with test after describe', function () {
    beforeEach(function () {
        $this->count++;
    });

    describe('foo', function () {});

    it('should run the before each', function () {
        expect($this->count)->toBe(2);
    });
});
