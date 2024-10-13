<?php

beforeEach(function () {
    $this->shouldSkip = true;
});

it('do not skips')
    ->skip(false)
    ->assertTrue(true);

it('skips with truthy')
    ->skip(1)
    ->assertTrue(false);

it('skips with truthy condition by default')
    ->skip()
    ->assertTrue(false);

it('skips with message')
    ->skip('skipped because bar')
    ->assertTrue(false);

it('skips with truthy closure condition')
    ->skip(function () {
        return '1';
    })
    ->assertTrue(false);

it('do not skips with falsy closure condition')
    ->skip(function () {
        return false;
    })
    ->assertTrue(true);

it('skips with condition and message')
    ->skip(true, 'skipped because foo')
    ->assertTrue(false);

it('skips when skip after assertion')
    ->assertTrue(true)
    ->skip();

it('can use something in the test case as a condition')
    ->skip(function () {
        return $this->shouldSkip;
    }, 'This test was skipped')
    ->assertTrue(false);

it('can user higher order callables and skip')
    ->skip(function () {
        return $this->shouldSkip;
    })
    ->expect(function () {
        return $this->shouldSkip;
    })
    ->toBeFalse();

describe('skip on describe', function () {
    beforeEach(function () {
        $this->ran = false;
    });

    afterEach(function () {
        match ($this->name()) {
            '__pest_evaluable__skip_on_describe__→__skipped_tests__→__nested_inside_skipped_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__skip_on_describe__→__skipped_tests__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__skip_on_describe__→_it_should_execute' => expect($this->ran)->toBe(true),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('skipped tests', function () {
        describe('nested inside skipped block', function () {
            it('should not execute', function () {
                $this->ran = true;
                $this->fail();
            });
        });

        it('should not execute', function () {
            $this->ran = true;
            $this->fail();
        });
    })->skip();

    it('should execute', function () {
        $this->ran = true;
        expect($this->ran)->toBe(true);
    });
});

describe('skip on beforeEach', function () {
    beforeEach(function () {
        $this->ran = false;
    });

    afterEach(function () {
        match ($this->name()) {
            '__pest_evaluable__skip_on_beforeEach__→__skipped_tests__→__nested_inside_skipped_block__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__skip_on_beforeEach__→__skipped_tests__→_it_should_not_execute' => expect($this->ran)->toBe(false),
            '__pest_evaluable__skip_on_beforeEach__→_it_should_execute' => expect($this->ran)->toBe(true),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('skipped tests', function () {
        beforeEach()->skip();

        describe('nested inside skipped block', function () {
            it('should not execute', function () {
                $this->ran = true;
                $this->fail();
            });
        });

        it('should not execute', function () {
            $this->ran = true;
            $this->fail();
        });
    });

    it('should execute', function () {
        $this->ran = true;
        expect($this->ran)->toBe(true);
    });
});

it('does not skip after the describe block', function () {
    expect(true)->toBeTrue();
});

it('can skip after the describe block', function () {
    expect(true)->toBeTrue();
})->skip();
