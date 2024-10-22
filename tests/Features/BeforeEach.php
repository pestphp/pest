<?php

beforeEach(function () {
    $this->bar = 2;
});

beforeEach(function () {
    $this->bar++;
});

beforeEach(function () {
    $this->bar = 0;
});

it('gets executed before each test', function () {
    expect($this->bar)->toBe(1);

    $this->bar = 'changed';
});

it('gets executed before each test once again', function () {
    expect($this->bar)->toBe(1);
});

beforeEach(function () {
    $this->bar++;
});

describe('outer', function () {
    beforeEach(function () {
        $this->bar++;
    });

    describe('inner', function () {
        beforeEach(function () {
            $this->bar++;
        });

        it('should call all parent beforeEach functions', function () {
            expect($this->bar)->toBe(3);
        });
    });
});

describe('with expectations', function () {
    beforeEach()->expect(true)->toBeTrue();

    describe('nested block', function () {
        test('test', function () {});
    });

    test('test', function () {});
});

describe('matching describe block names', function () {
    beforeEach(function () {
        $this->foo = 1;
    });

    describe('outer', function () {
        beforeEach(function () {
            $this->foo++;
        });

        describe('middle', function () {
            beforeEach(function () {
                $this->foo++;
            });

            describe('inner', function () {
                beforeEach(function () {
                    $this->foo++;
                });

                it('should call all parent beforeEach functions', function () {
                    expect($this->foo)->toBe(4);
                });
            });
        });

        describe('middle', function () {
            it('should not call beforeEach functions for sibling describe blocks with the same name', function () {
                expect($this->foo)->toBe(2);
            });
        });

        describe('inner', function () {
            it('should not call beforeEach functions for descendent of sibling describe blocks with the same name', function () {
                expect($this->foo)->toBe(2);
            });
        });
    });
});

$matchingNameCalls = 0;
describe('matching name', function () use (&$matchingNameCalls) {
    beforeEach(function () use (&$matchingNameCalls) {
        $matchingNameCalls++;
    });

    it('should call the before each', function () use (&$matchingNameCalls) {
        expect($matchingNameCalls)->toBe(1);
    });
});

describe('matching name', function () use (&$matchingNameCalls) {
    it('should not call the before each on the describe block with the same name', function () use (&$matchingNameCalls) {
        expect($matchingNameCalls)->toBe(1);
    });
});

beforeEach(function () {
    $this->baz = 1;
});

describe('called on all tests', function () {
    beforeEach(function () {
        $this->baz++;
    });

    test('beforeEach should be called', function () {
        expect($this->baz)->toBe(2);
    });

    test('beforeEach should be called for all tests', function () {
        expect($this->baz)->toBe(2);
    });
});
