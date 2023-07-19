<?php

it('allows access to the underlying expectNotToPerformAssertions method', function () {
    $this->expectNotToPerformAssertions();

    $result = 1 + 1;
});

it('allows performing no expectations without being risky', function () {
    $result = 1 + 1;
})->throwsNoExceptions();

describe('a "describe" group of tests', function () {
    it('allows performing no expectations without being risky', function () {
        $result = 1 + 1;
    });
})->throwsNoExceptions();
