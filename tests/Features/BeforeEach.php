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
