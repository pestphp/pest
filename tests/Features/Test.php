<?php

test('a test', function () {
    assertArrayHasKey('key', ['key' => 'foo']);
});

test('higher order message test')->assertTrue(true);
