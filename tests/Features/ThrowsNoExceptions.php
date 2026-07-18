<?php

it('allows access to the underlying expectNotToPerformAssertions method', function (): void {
    $this->expectNotToPerformAssertions();

    $result = 1 + 1;
});

it('allows performing no expectations without being risky', function (): void {
    $result = 1 + 1;
})->throwsNoExceptions();

describe('a "describe" group of tests', function (): void {
    it('allows performing no expectations without being risky', function (): void {
        $result = 1 + 1;
    });
})->throwsNoExceptions();
