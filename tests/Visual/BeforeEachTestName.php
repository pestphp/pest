<?php

beforeEach(fn () => $this->latestDescription = self::$__latestDescription);

test('latest description', function () {
    expect($this->latestDescription)->toBe('latest description');
});
