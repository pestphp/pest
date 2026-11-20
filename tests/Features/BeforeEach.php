<?php

beforeEach(function (): void {
    $this->bar = 2;
});

beforeEach(function (): void {
    $this->bar++;
});

beforeEach(function (): void {
    $this->bar = 0;
});

it('gets executed before each test', function (): void {
    expect($this->bar)->toBe(1);

    $this->bar = 'changed';
});

it('gets executed before each test once again', function (): void {
    expect($this->bar)->toBe(1);
});

beforeEach(function (): void {
    $this->bar++;
});

describe('outer', function (): void {
    beforeEach(function (): void {
        $this->bar++;
    });

    describe('inner', function (): void {
        beforeEach(function (): void {
            $this->bar++;
        });

        it('should call all parent beforeEach functions', function (): void {
            expect($this->bar)->toBe(3);
        });
    });
});

describe('with expectations', function (): void {
    beforeEach()->expect(true)->toBeTrue();

    describe('nested block', function (): void {
        test('test', function (): void {});
    });

    test('test', function (): void {});
});

describe('matching describe block names', function (): void {
    beforeEach(function (): void {
        $this->foo = 1;
    });

    describe('outer', function (): void {
        beforeEach(function (): void {
            $this->foo++;
        });

        describe('middle', function (): void {
            beforeEach(function (): void {
                $this->foo++;
            });

            describe('inner', function (): void {
                beforeEach(function (): void {
                    $this->foo++;
                });

                it('should call all parent beforeEach functions', function (): void {
                    expect($this->foo)->toBe(4);
                });
            });
        });

        describe('middle', function (): void {
            it('should not call beforeEach functions for sibling describe blocks with the same name', function (): void {
                expect($this->foo)->toBe(2);
            });
        });

        describe('inner', function (): void {
            it('should not call beforeEach functions for descendent of sibling describe blocks with the same name', function (): void {
                expect($this->foo)->toBe(2);
            });
        });
    });
});

$matchingNameCalls = 0;
describe('matching name', function () use (&$matchingNameCalls): void {
    beforeEach(function () use (&$matchingNameCalls): void {
        $matchingNameCalls++;
    });

    it('should call the before each', function () use (&$matchingNameCalls): void {
        expect($matchingNameCalls)->toBe(1);
    });
});

describe('matching name', function () use (&$matchingNameCalls): void {
    it('should not call the before each on the describe block with the same name', function () use (&$matchingNameCalls): void {
        expect($matchingNameCalls)->toBe(1);
    });
});

beforeEach(function (): void {
    $this->baz = 1;
});

describe('called on all tests', function (): void {
    beforeEach(function (): void {
        $this->baz++;
    });

    test('beforeEach should be called', function (): void {
        expect($this->baz)->toBe(2);
    });

    test('beforeEach should be called for all tests', function (): void {
        expect($this->baz)->toBe(2);
    });
});

describe('hierarchical test naming', function (): void {
    describe('block one', function (): void {
        describe('the same name', function (): void {
            it('does not call beforeEach from sibling describe with same name', function (): void {
                expect($this)->not->toHaveProperty('example');
            });
        });
    });

    describe('block two', function (): void {
        beforeEach(function (): void {
            $this->example = false;
        });

        describe('the same name', function (): void {
            beforeEach(function (): void {
                $this->result = $this->example;
            });

            it('correctly calls beforeEach from own describe hierarchy', function (): void {
                expect($this->result)->toBeFalse();
            });
        });
    });
});
