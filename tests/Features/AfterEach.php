<?php

$state = new stdClass;

beforeEach(function () use ($state) {
    $this->state = $state;
});

afterEach(function () {
    $this->state->bar = 1;
});

afterEach(function () {
    unset($this->state->bar);
});

it('does not get executed before the test', function () {
    expect($this->state)->not->toHaveProperty('bar');
});

it('gets executed after the test', function () {
    expect($this->state)->toHaveProperty('bar');
    expect($this->state->bar)->toBe(2);
});

afterEach(function () {
    $this->state->bar = 2;
});

describe('outer', function () {
    afterEach(function () {
        $this->state->bar++;
    });

    describe('inner', function () {
        afterEach(function () {
            $this->state->bar++;
        });

        it('does not get executed before the test', function () {
            expect($this->state)->toHaveProperty('bar');
            expect($this->state->bar)->toBe(2);
        });

        it('should call all parent afterEach functions', function () {
            expect($this->state)->toHaveProperty('bar');
            expect($this->state->bar)->toBe(4);
        });
    });
});

describe('matching describe block names', function () {
    afterEach(function () {
        $this->state->foo = 1;
    });

    describe('outer', function () {
        afterEach(function () {
            $this->state->foo++;
        });

        describe('middle', function () {
            afterEach(function () {
                $this->state->foo++;
            });

            describe('inner', function () {
                afterEach(function () {
                    $this->state->foo++;
                });

                it('does not get executed before the test', function () {
                    expect($this)->not->toHaveProperty('foo');
                });

                it('should call all parent afterEach functions', function () {
                    expect($this->state->foo)->toBe(4);
                });
            });
        });

        describe('middle', function () {
            it('does not get executed before the test', function () {
                expect($this)->not->toHaveProperty('foo');
            });

            it('should not call afterEach functions for sibling describe blocks with the same name', function () {
                expect($this)->not->toHaveProperty('foo');
            });
        });

        describe('inner', function () {
            it('does not get executed before the test', function () {
                expect($this)->not->toHaveProperty('foo');
            });

            it('should not call afterEach functions for descendent of sibling describe blocks with the same name', function () {
                expect($this)->not->toHaveProperty('foo');
            });
        });
    });
});
