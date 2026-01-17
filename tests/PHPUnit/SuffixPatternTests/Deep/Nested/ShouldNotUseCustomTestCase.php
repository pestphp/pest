<?php

test('this file should NOT use CustomTestCase', function () {
    expect($this)->not->toBeInstanceOf(Tests\CustomTestCase\SuffixedTest::class);
});
