<?php

beforeEach(function (): void {
    $this->description = $this->__description;
    $this->latestDescription = self::$__latestDescription;
});

test('description', function (): void {
    expect($this->description)->toBe('description');
});

test('latest description', function (): void {
    expect($this->latestDescription)->toBe('latest description');
});
