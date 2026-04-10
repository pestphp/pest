<?php

/**
 * Tests for dataset method chaining with hooks and describe blocks.
 *
 * Covers the fix from PR #1565: beforeEach()->with(), describe()->with(),
 * and nested describe blocks with datasets.
 */

// ---------------------------------------------------------------
// beforeEach()->with() inside describe blocks
// ---------------------------------------------------------------

describe('beforeEach()->with() applies dataset to tests', function () {
    beforeEach()->with([10]);

    test('receives the dataset value', function ($value) {
        expect($value)->toBe(10);
    });

    it('also receives the dataset value in it()', function ($value) {
        expect($value)->toBe(10);
    });
});

describe('beforeEach()->with() with multiple dataset values', function () {
    beforeEach()->with([1, 2, 3]);

    test('receives each value from the dataset', function ($value) {
        expect($value)->toBeIn([1, 2, 3]);
    });
});

describe('beforeEach()->with() with keyed dataset', function () {
    beforeEach()->with(['first' => [10], 'second' => [20]]);

    test('receives keyed dataset values', function ($value) {
        expect($value)->toBeIn([10, 20]);
    });
});

describe('beforeEach()->with() with closure dataset', function () {
    beforeEach()->with(function () {
        yield [100];
        yield [200];
    });

    test('receives values from closure dataset', function ($value) {
        expect($value)->toBeIn([100, 200]);
    });
});

// ---------------------------------------------------------------
// describe()->with() method chaining
// ---------------------------------------------------------------

describe('describe()->with() passes dataset to tests', function () {
    test('receives the dataset value', function ($value) {
        expect($value)->toBe(42);
    });

    it('also receives it in it()', function ($value) {
        expect($value)->toBe(42);
    });
})->with([42]);

describe('describe()->with() with multiple values', function () {
    test('receives each value', function ($value) {
        expect($value)->toBeIn([5, 10, 15]);
    });
})->with([5, 10, 15]);

describe('describe()->with() with keyed dataset', function () {
    test('receives keyed values', function ($value) {
        expect($value)->toBeIn([100, 200]);
    });
})->with(['alpha' => [100], 'beta' => [200]]);

describe('describe()->with() with closure dataset', function () {
    test('receives closure dataset values', function ($value) {
        expect($value)->toBeIn([7, 14]);
    });
})->with(function () {
    yield [7];
    yield [14];
});

// ---------------------------------------------------------------
// Nested describe blocks with datasets
// ---------------------------------------------------------------

describe('outer with dataset', function () {
    describe('inner without dataset', function () {
        test('inherits outer dataset', function (...$args) {
            expect($args)->toBe([1]);
        });
    });
})->with([1]);

describe('nested describe blocks with datasets at multiple levels', function () {
    describe('level 1', function () {
        test('receives level 1 dataset', function (...$args) {
            expect($args)->toBe([10]);
        });

        describe('level 2', function () {
            test('receives datasets from all ancestor levels', function (...$args) {
                expect($args)->toBe([10, 20]);
            });
        })->with([20]);
    })->with([10]);
});

describe('deeply nested describe with datasets', function () {
    describe('a', function () {
        describe('b', function () {
            describe('c', function () {
                test('receives all ancestor datasets', function (...$args) {
                    expect($args)->toBe([1, 2, 3]);
                });
            })->with([3]);
        })->with([2]);
    })->with([1]);
});

// ---------------------------------------------------------------
// Combining hook datasets with test-level datasets
// ---------------------------------------------------------------

describe('beforeEach()->with() combined with test->with()', function () {
    beforeEach()->with([10]);

    test('receives both datasets as cross product', function ($hookValue, $testValue) {
        expect($hookValue)->toBe(10);
        expect($testValue)->toBeIn([1, 2]);
    })->with([1, 2]);
});

describe('describe()->with() combined with test->with()', function () {
    test('receives both datasets', function ($describeValue, $testValue) {
        expect($describeValue)->toBe(5);
        expect($testValue)->toBeIn([50, 60]);
    })->with([50, 60]);
})->with([5]);

// ---------------------------------------------------------------
// beforeEach()->with() combined with beforeEach closure
// ---------------------------------------------------------------

describe('beforeEach closure and beforeEach()->with() coexist', function () {
    beforeEach(function () {
        $this->setupValue = 'initialized';
    });

    beforeEach()->with([99]);

    test('has both the closure state and dataset', function ($value) {
        expect($this->setupValue)->toBe('initialized');
        expect($value)->toBe(99);
    });
});

describe('beforeEach()->with() does not interfere with closure hooks', function () {
    beforeEach(function () {
        $this->counter = 1;
    });

    beforeEach(function () {
        $this->counter++;
    });

    beforeEach()->with([42]);

    test('closures run in order and dataset is applied', function ($value) {
        expect($this->counter)->toBe(2);
        expect($value)->toBe(42);
    });
});

// ---------------------------------------------------------------
// Dataset isolation between describe blocks
// ---------------------------------------------------------------

describe('first describe with dataset', function () {
    beforeEach()->with([111]);

    test('gets its own dataset', function ($value) {
        expect($value)->toBe(111);
    });
});

describe('second describe with different dataset', function () {
    beforeEach()->with([222]);

    test('gets its own dataset, not the sibling', function ($value) {
        expect($value)->toBe(222);
    });
});

describe('third describe without dataset', function () {
    test('has no dataset leaking from siblings', function () {
        expect(true)->toBeTrue();
    });
});

// ---------------------------------------------------------------
// describe()->with() combined with beforeEach hooks
// ---------------------------------------------------------------

describe('describe()->with() with beforeEach closure', function () {
    beforeEach(function () {
        $this->hookRan = true;
    });

    test('both hook and dataset work', function ($value) {
        expect($this->hookRan)->toBeTrue();
        expect($value)->toBe(77);
    });
})->with([77]);

describe('describe()->with() with afterEach closure', function () {
    afterEach(function () {
        expect($this->value)->toBe(88);
    });

    test('dataset is available and afterEach runs', function ($value) {
        $this->value = $value;
        expect($value)->toBe(88);
    });
})->with([88]);

// ---------------------------------------------------------------
// Multiple tests in a describe with beforeEach()->with()
// ---------------------------------------------------------------

describe('multiple tests share the same beforeEach dataset', function () {
    beforeEach()->with([33]);

    test('first test gets the dataset', function ($value) {
        expect($value)->toBe(33);
    });

    test('second test also gets the dataset', function ($value) {
        expect($value)->toBe(33);
    });

    it('third test with it() also gets the dataset', function ($value) {
        expect($value)->toBe(33);
    });
});

// ---------------------------------------------------------------
// Nested describe with beforeEach()->with() at inner level
// ---------------------------------------------------------------

describe('outer describe', function () {
    beforeEach(function () {
        $this->outer = true;
    });

    describe('inner describe with dataset on hook', function () {
        beforeEach()->with([55]);

        test('inherits outer beforeEach and has inner dataset', function ($value) {
            expect($this->outer)->toBeTrue();
            expect($value)->toBe(55);
        });
    });

    test('outer test is unaffected by inner dataset', function () {
        expect($this->outer)->toBeTrue();
    });
});

// ---------------------------------------------------------------
// describe()->with() with depends
// ---------------------------------------------------------------

describe('describe()->with() preserves depends', function () {
    test('first', function ($value) {
        expect($value)->toBe(9);
    });

    test('second', function ($value) {
        expect($value)->toBe(9);
    })->depends('first');
})->with([9]);
