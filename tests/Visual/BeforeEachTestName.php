<?php

beforeEach(function () {
    $this->description = $this->__description;
    $this->latestDescription = self::$__latestDescription;
});

test('description', function () {
    expect($this->description)->toBe('description');
});

test('latest description', function () {
    expect($this->latestDescription)->toBe('latest description');
});
