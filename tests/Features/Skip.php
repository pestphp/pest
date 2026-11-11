<?php

beforeEach(function (): void {
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
    ->skip(fn (): string => '1')
    ->assertTrue(false);

it('do not skips with falsy closure condition')
    ->skip(fn (): false => false)
    ->assertTrue(true);

it('skips with condition and message')
    ->skip(true, 'skipped because foo')
    ->assertTrue(false);

it('skips when skip after assertion')
    ->assertTrue(true)
    ->skip();

it('can use something in the test case as a condition')
    ->skip(fn () => $this->shouldSkip, 'This test was skipped')
    ->assertTrue(false);

it('can user higher order callables and skip')
    ->skip(fn () => $this->shouldSkip)
    ->expect(fn () => $this->shouldSkip)
    ->toBeFalse();

describe('skip on describe', function (): void {
    beforeEach(function (): void {
        $this->ran = false;
    });

    afterEach(function (): void {
        match ($this->name()) {
            '__pest_evaluable__skip_on_describe__→__skipped_tests__→__nested_inside_skipped_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__skip_on_describe__→__skipped_tests__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__skip_on_describe__→_it_should_execute' => expect($this->ran)->toBeTrue(),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('skipped tests', function (): void {
        describe('nested inside skipped block', function (): void {
            it('should not execute', function (): void {
                $this->ran = true;
                $this->fail();
            });
        });

        it('should not execute', function (): void {
            $this->ran = true;
            $this->fail();
        });
    })->skip();

    it('should execute', function (): void {
        $this->ran = true;
        expect($this->ran)->toBeTrue();
    });
});

describe('skip on beforeEach', function (): void {
    beforeEach(function (): void {
        $this->ran = false;
    });

    afterEach(function (): void {
        match ($this->name()) {
            '__pest_evaluable__skip_on_beforeEach__→__skipped_tests__→__nested_inside_skipped_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__skip_on_beforeEach__→__skipped_tests__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__skip_on_beforeEach__→_it_should_execute' => expect($this->ran)->toBeTrue(),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('skipped tests', function (): void {
        beforeEach()->skip();

        describe('nested inside skipped block', function (): void {
            it('should not execute', function (): void {
                $this->ran = true;
                $this->fail();
            });
        });

        it('should not execute', function (): void {
            $this->ran = true;
            $this->fail();
        });
    });

    it('should execute', function (): void {
        $this->ran = true;
        expect($this->ran)->toBeTrue();
    });
});

describe('matching describe with skip', function (): void {
    beforeEach(function (): void {
        $this->ran = false;
    });

    afterEach(function (): void {
        match ($this->name()) {
            '__pest_evaluable__matching_describe_with_skip__→__describe_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__matching_describe_with_skip__→__describe_block__→_it_should_execute_a_test_in_a_describe_block_with_the_same_name_as_a_skipped_describe_block' => expect($this->ran)->toBeTrue(),
            '__pest_evaluable__matching_describe_with_skip__→_it_should_execute' => expect($this->ran)->toBeTrue(),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('describe block', function (): void {
        it('should not execute', function (): void {
            $this->ran = true;
            $this->fail();
        });
    })->skip();

    describe('describe block', function (): void {
        it('should execute a test in a describe block with the same name as a skipped describe block', function (): void {
            $this->ran = true;
        });
    });

    it('should execute', function (): void {
        $this->ran = true;
        expect($this->ran)->toBeTrue();
    });
});

describe('matching describe with skip on beforeEach', function (): void {
    beforeEach(function (): void {
        $this->ran = false;
    });

    afterEach(function (): void {
        match ($this->name()) {
            '__pest_evaluable__matching_describe_with_skip_on_beforeEach__→__describe_block__→_it_should_not_execute' => expect($this->ran)->toBeFalse(),
            '__pest_evaluable__matching_describe_with_skip_on_beforeEach__→__describe_block__→_it_should_execute_a_test_in_a_describe_block_with_the_same_name_as_a_skipped_describe_block' => expect($this->ran)->toBeTrue(),
            '__pest_evaluable__matching_describe_with_skip_on_beforeEach__→_it_should_execute' => expect($this->ran)->toBeTrue(),
            default => $this->fail('Unexpected test name: '.$this->name()),
        };
    });

    describe('describe block', function (): void {
        beforeEach()->skip();

        it('should not execute', function (): void {
            $this->ran = true;
            $this->fail();
        });
    });

    describe('describe block', function (): void {
        it('should execute a test in a describe block with the same name as a skipped describe block', function (): void {
            $this->ran = true;
        });
    });

    it('should execute', function (): void {
        $this->ran = true;
        expect($this->ran)->toBeTrue();
    });
});

it('does not skip after the describe block', function (): void {
    expect(true)->toBeTrue();
});

it('can skip after the describe block', function (): void {
    expect(true)->toBeTrue();
})->skip();
