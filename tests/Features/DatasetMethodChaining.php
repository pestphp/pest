<?php

describe('beforeEach()->with() applies dataset to tests', function (): void {
    beforeEach()->with([10]);

    test('receives the dataset value', function ($value): void {
        expect($value)->toBe(10);
    });

    it('also receives the dataset value in it()', function ($value): void {
        expect($value)->toBe(10);
    });
});

describe('beforeEach()->with() with multiple dataset values', function (): void {
    beforeEach()->with([1, 2, 3]);

    test('receives each value from the dataset', function ($value): void {
        expect($value)->toBeIn([1, 2, 3]);
    });
});

describe('beforeEach()->with() with keyed dataset', function (): void {
    beforeEach()->with(['first' => [10], 'second' => [20]]);

    test('receives keyed dataset values', function ($value): void {
        expect($value)->toBeIn([10, 20]);
    });
});

describe('beforeEach()->with() with closure dataset', function (): void {
    beforeEach()->with(function () {
        yield [100];
        yield [200];
    });

    test('receives values from closure dataset', function ($value): void {
        expect($value)->toBeIn([100, 200]);
    });
});

describe('describe()->with() passes dataset to tests', function (): void {
    test('receives the dataset value', function ($value): void {
        expect($value)->toBe(42);
    });

    it('also receives it in it()', function ($value): void {
        expect($value)->toBe(42);
    });
})->with([42]);

describe('describe()->with() with multiple values', function (): void {
    test('receives each value', function ($value): void {
        expect($value)->toBeIn([5, 10, 15]);
    });
})->with([5, 10, 15]);

describe('describe()->with() with keyed dataset', function (): void {
    test('receives keyed values', function ($value): void {
        expect($value)->toBeIn([100, 200]);
    });
})->with(['alpha' => [100], 'beta' => [200]]);

describe('describe()->with() with closure dataset', function (): void {
    test('receives closure dataset values', function ($value): void {
        expect($value)->toBeIn([7, 14]);
    });
})->with(function () {
    yield [7];
    yield [14];
});

describe('outer with dataset', function (): void {
    describe('inner without dataset', function (): void {
        test('inherits outer dataset', function (...$args): void {
            expect($args)->toBe([1]);
        });
    });
})->with([1]);

describe('nested describe blocks with datasets at multiple levels', function (): void {
    describe('level 1', function (): void {
        test('receives level 1 dataset', function (...$args): void {
            expect($args)->toBe([10]);
        });

        describe('level 2', function (): void {
            test('receives datasets from all ancestor levels', function (...$args): void {
                expect($args)->toBe([10, 20]);
            });
        })->with([20]);
    })->with([10]);
});

describe('deeply nested describe with datasets', function (): void {
    describe('a', function (): void {
        describe('b', function (): void {
            describe('c', function (): void {
                test('receives all ancestor datasets', function (...$args): void {
                    expect($args)->toBe([1, 2, 3]);
                });
            })->with([3]);
        })->with([2]);
    })->with([1]);
});

describe('beforeEach()->with() combined with test->with()', function (): void {
    beforeEach()->with([10]);

    test('receives both datasets as cross product', function ($hookValue, $testValue): void {
        expect($hookValue)->toBe(10)
            ->and($testValue)->toBeIn([1, 2]);
    })->with([1, 2]);
});

describe('describe()->with() combined with test->with()', function (): void {
    test('receives both datasets', function ($describeValue, $testValue): void {
        expect($describeValue)->toBe(5)
            ->and($testValue)->toBeIn([50, 60]);
    })->with([50, 60]);
})->with([5]);

describe('beforeEach closure and beforeEach()->with() coexist', function (): void {
    beforeEach(function (): void {
        $this->setupValue = 'initialized';
    });

    beforeEach()->with([99]);

    test('has both the closure state and dataset', function ($value): void {
        expect($this->setupValue)->toBe('initialized')
            ->and($value)->toBe(99);
    });
});

describe('beforeEach()->with() does not interfere with closure hooks', function (): void {
    beforeEach(function (): void {
        $this->counter = 1;
    });

    beforeEach(function (): void {
        $this->counter++;
    });

    beforeEach()->with([42]);

    test('closures run in order and dataset is applied', function ($value): void {
        expect($this->counter)->toBe(2)
            ->and($value)->toBe(42);
    });
});

describe('first describe with dataset', function (): void {
    beforeEach()->with([111]);

    test('gets its own dataset', function ($value): void {
        expect($value)->toBe(111);
    });
});

describe('second describe with different dataset', function (): void {
    beforeEach()->with([222]);

    test('gets its own dataset, not the sibling', function ($value): void {
        expect($value)->toBe(222);
    });
});

describe('third describe without dataset', function (): void {
    test('has no dataset leaking from siblings', function (): void {
        expect(true)->toBeTrue();
    });
});

describe('describe()->with() with beforeEach closure', function (): void {
    beforeEach(function (): void {
        $this->hookRan = true;
    });

    test('both hook and dataset work', function ($value): void {
        expect($this->hookRan)->toBeTrue()
            ->and($value)->toBe(77);
    });
})->with([77]);

describe('describe()->with() with afterEach closure', function (): void {
    afterEach(function (): void {
        expect($this->value)->toBe(88);
    });

    test('dataset is available and afterEach runs', function ($value): void {
        $this->value = $value;
        expect($value)->toBe(88);
    });
})->with([88]);

describe('multiple tests share the same beforeEach dataset', function (): void {
    beforeEach()->with([33]);

    test('first test gets the dataset', function ($value): void {
        expect($value)->toBe(33);
    });

    test('second test also gets the dataset', function ($value): void {
        expect($value)->toBe(33);
    });

    it('third test with it() also gets the dataset', function ($value): void {
        expect($value)->toBe(33);
    });
});

describe('outer describe', function (): void {
    beforeEach(function (): void {
        $this->outer = true;
    });

    describe('inner describe with dataset on hook', function (): void {
        beforeEach()->with([55]);

        test('inherits outer beforeEach and has inner dataset', function ($value): void {
            expect($this->outer)->toBeTrue()
                ->and($value)->toBe(55);
        });
    });

    test('outer test is unaffected by inner dataset', function (): void {
        expect($this->outer)->toBeTrue();
    });
});

describe('describe()->with() preserves depends', function (): void {
    test('first', function ($value): void {
        expect($value)->toBe(9);
    });

    test('second', function ($value): void {
        expect($value)->toBe(9);
    })->depends('first');
})->with([9]);
