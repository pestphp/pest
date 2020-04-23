<?php

it('is a test', function () {
    assertArrayHasKey('key', ['key' => 'foo']);
});

it('is a higher order message test')->assertTrue(true);
