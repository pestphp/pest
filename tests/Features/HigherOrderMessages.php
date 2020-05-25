<?php

beforeEach()->assertTrue(true);

it('proxies calls to object')->assertTrue(true);

it('proxies multiple calls to object')
    ->assertTrue(true)
    ->assertTrue(true);

afterEach()
    ->assertTrue(true)
    ->assertArrayHasKey('key', ['key' => 'value']);
