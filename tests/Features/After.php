<?php

beforeEach(function () {
    $this->count = 0;
});

afterEach(function () {
    match ($this->name()) {
        '__pest_evaluable_it_can_run_after_test' => expect($this->count)->toBe(1),
        '__pest_evaluable_it_can_run_after_test_twice' => expect($this->count)->toBe(1),
        '__pest_evaluable_it_does_not_run_when_skipped' => expect($this->count)->toBe(0),
        '__pest_evaluable__something__→_it_does_not_run_when_skipped' => expect($this->count)->toBe(0),
        '__pest_evaluable__something__→_it_can_run_after_test' => expect($this->count)->toBe(1),
        '__pest_evaluable__something_2__→_it_can_run_after_test' => expect($this->count)->toBe(1),
        '__pest_evaluable_high_order_test' => expect($this->count)->toBe(1),
        '__pest_evaluable_high_order_test_with_skip' => expect($this->count)->toBe(0),
        '__pest_evaluable_post__foo__→_defer_Closure_Object____→_expect_Closure_Object____→_toBe_1' => expect($this->count)->toBe(1),
        default => $this->fail('Unexpected test name: '.$this->name()),
    };

    $this->count++;
});

it('can run after test', function () {
    expect($this->count)->toBe(0);

    $this->count++;
})->after(function () {
    expect($this->count)->toBe(2);

    $this->count++;
});

it('can run after test twice', function () {
    expect($this->count)->toBe(0);

    $this->count++;
})->after(function () {
    expect($this->count)->toBe(2);

    $this->count++;
})->after(function () {
    expect($this->count)->toBe(3);

    $this->count++;
});

it('does not run when skipped', function () {
    dd('This should not run 1');
})->skip()->after(function () {
    dd('This should not run 2');
});

afterEach(function () {
    match ($this->name()) {
        '__pest_evaluable_it_can_run_after_test' => expect($this->count)->toBe(3),
        '__pest_evaluable_it_can_run_after_test_twice' => expect($this->count)->toBe(4),
        '__pest_evaluable_it_does_not_run_when_skipped' => expect($this->count)->toBe(1),
        '__pest_evaluable__something__→_it_does_not_run_when_skipped' => expect($this->count)->toBe(1),
        '__pest_evaluable__something__→_it_can_run_after_test' => expect($this->count)->toBe(2),
        '__pest_evaluable__something_2__→_it_can_run_after_test' => expect($this->count)->toBe(2),
        '__pest_evaluable_high_order_test' => expect($this->count)->toBe(2),
        '__pest_evaluable_high_order_test_with_skip' => expect($this->count)->toBe(1),
        '__pest_evaluable_post__foo__→_defer_Closure_Object____→_expect_Closure_Object____→_toBe_1' => expect($this->count)->toBe(2),

        default => $this->fail('Unexpected test name: '.$this->name()),

    };

    $this->count++;
});

afterEach(function () {
    match ($this->name()) {
        '__pest_evaluable_it_can_run_after_test' => expect($this->count)->toBe(4),
        '__pest_evaluable_it_can_run_after_test_twice' => expect($this->count)->toBe(5),
        '__pest_evaluable_it_does_not_run_when_skipped' => expect($this->count)->toBe(2),
        '__pest_evaluable__something__→_it_does_not_run_when_skipped' => expect($this->count)->toBe(2),
        '__pest_evaluable__something__→_it_can_run_after_test' => expect($this->count)->toBe(3),
        '__pest_evaluable__something_2__→_it_can_run_after_test' => expect($this->count)->toBe(3),
        '__pest_evaluable_high_order_test' => expect($this->count)->toBe(3),
        '__pest_evaluable_high_order_test_with_skip' => expect($this->count)->toBe(2),
        '__pest_evaluable_post__foo__→_defer_Closure_Object____→_expect_Closure_Object____→_toBe_1' => expect($this->count)->toBe(3),
        default => $this->fail('Unexpected test name: '.$this->name()),
    };

    $this->count++;
});

describe('something', function () {
    it('does not run when skipped', function () {
        dd('This should not run 3');
    })->skip()->after(function () {
        dd('This should not run 4');
    });

    it('can run after test', function () {
        expect($this->count)->toBe(0);

        $this->count++;
    })->after(function () {
        expect($this->count)->toBe(5);

        $this->count++;
    })->after(function () {
        expect($this->count)->toBe(6);

        $this->count++;
    });
})->after(function () {
    expect($this->count)->toBe(4);

    $this->count++;
});

describe('something 2', function () {
    it('can run after test', function () {
        expect($this->count)->toBe(0);

        $this->count++;
    })->after(function () {
        expect($this->count)->toBe(6);

        $this->count++;
    });
})->after(function () {
    expect($this->count)->toBe(4);

    $this->count++;
})->after(function () {
    expect($this->count)->toBe(5);

    $this->count++;
});

test('high order test')
    ->defer(fn () => $this->count++)
    ->expect(fn () => $this->count)->toBe(1)
    ->after(function () {
        expect($this->count)->toBe(4);

        $this->count++;
    });

test('high order test with skip')
    ->skip()
    ->defer(fn () => $this->count++)
    ->expect(fn () => $this->count)->toBe(1)
    ->after(function () {
        dd('This should not run 5');
    });

pest()->use(Postable::class);

/**
 * @return TestCase|TestCall|Gettable
 */
function post(string $route)
{
    return test()->post($route);
}

trait Postable
{
    /**
     * @return TestCase|TestCall|Gettable
     */
    public function post(string $route)
    {
        expect($route)->not->toBeEmpty();

        return $this;
    }
}

post('foo')->defer(fn () => $this->count++)->expect(fn () => $this->count)->toBe(1);
