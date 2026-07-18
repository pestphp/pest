<?php

$state = new stdClass;

beforeEach(function () use ($state): void {
    $this->state = $state;
});

afterEach(function (): void {
    $this->state->bar = 1;
});

afterEach(function (): void {
    unset($this->state->bar);
});

it('does not get executed before the test', function (): void {
    expect($this->state)->not->toHaveProperty('bar');
});

it('gets executed after the test', function (): void {
    expect($this->state)->toHaveProperty('bar')
        ->and($this->state->bar)->toBe(2);
});

afterEach(function (): void {
    $this->state->bar = 2;
});

describe('outer', function (): void {
    afterEach(function (): void {
        $this->state->bar++;
    });

    describe('inner', function (): void {
        afterEach(function (): void {
            $this->state->bar++;
        });

        it('does not get executed before the test', function (): void {
            expect($this->state)->toHaveProperty('bar')
                ->and($this->state->bar)->toBe(2);
        });

        it('should call all parent afterEach functions', function (): void {
            expect($this->state)->toHaveProperty('bar')
                ->and($this->state->bar)->toBe(4);
        });
    });
});

describe('matching describe block names', function (): void {
    afterEach(function (): void {
        $this->state->foo = 1;
    });

    describe('outer', function (): void {
        afterEach(function (): void {
            $this->state->foo++;
        });

        describe('middle', function (): void {
            afterEach(function (): void {
                $this->state->foo++;
            });

            describe('inner', function (): void {
                afterEach(function (): void {
                    $this->state->foo++;
                });

                it('does not get executed before the test', function (): void {
                    expect($this)->not->toHaveProperty('foo');
                });

                it('should call all parent afterEach functions', function (): void {
                    expect($this->state->foo)->toBe(4);
                });
            });
        });

        describe('middle', function (): void {
            it('does not get executed before the test', function (): void {
                expect($this)->not->toHaveProperty('foo');
            });

            it('should not call afterEach functions for sibling describe blocks with the same name', function (): void {
                expect($this)->not->toHaveProperty('foo');
            });
        });

        describe('inner', function (): void {
            it('does not get executed before the test', function (): void {
                expect($this)->not->toHaveProperty('foo');
            });

            it('should not call afterEach functions for descendent of sibling describe blocks with the same name', function (): void {
                expect($this)->not->toHaveProperty('foo');
            });
        });
    });
});
