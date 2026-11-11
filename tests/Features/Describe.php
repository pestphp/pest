<?php

beforeEach(fn () => $this->count = 1);

test('before each', function (): void {
    expect($this->count)->toBe(1);
});

describe('hooks', function (): void {
    beforeEach(function (): void {
        $this->count++;
    });

    test('value', function (): void {
        expect($this->count)->toBe(2);
        $this->count++;
    });

    afterEach(function (): void {
        expect($this->count)->toBe(3);
    });
});

describe('hooks in different orders', function (): void {
    beforeEach(function (): void {
        $this->count++;
    });

    test('value', function (): void {
        expect($this->count)->toBe(3);
        $this->count++;
    });

    afterEach(function (): void {
        expect($this->count)->toBe(4);
    });

    beforeEach(function (): void {
        $this->count++;
    });
});

test('todo')->todo()->shouldNotRun();

test('previous describable before each does not get applied here', function (): void {
    expect($this->count)->toBe(1);
});

describe('todo on hook', function (): void {
    beforeEach()->todo();

    test('should not fail')->shouldNotRun();
    test('should run')->expect(true)->toBeTrue();
});

describe('todo on describe', function (): void {
    test('should not fail')->shouldNotRun();

    test('should run')->expect(true)->toBeTrue();
})->todo();

test('should run')->expect(true)->toBeTrue();

test('with', fn ($foo) => expect($foo)->toBe(1))->with([1]);

describe('with on hook', function (): void {
    beforeEach()->with([2]);

    test('value', function ($foo): void {
        expect($foo)->toBe(2);
    });
});

describe('with on describe', function (): void {
    test('value', function ($foo): void {
        expect($foo)->toBe(3);
    });
})->with([3]);

describe('depends on describe', function (): void {
    test('foo', function (): void {
        expect('foo')->toBe('foo');
    });

    test('bar', function (): void {
        expect('bar')->toBe('bar');
    })->depends('foo');
});

describe('depends on describe using with', function (): void {
    test('foo', function ($foo): void {
        expect($foo)->toBe(3);
    });

    test('bar', function ($foo): void {
        expect($foo + $foo)->toBe(6);
    })->depends('foo');
})->with([3]);

describe('with test after describe', function (): void {
    beforeEach(function (): void {
        $this->count++;
    });

    describe('foo', function (): void {});

    it('should run the before each', function (): void {
        expect($this->count)->toBe(2);
    });
});
