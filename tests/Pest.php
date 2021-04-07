<?php

uses()->group('integration')->in('Visual');

$globalHook = (object) []; // NOTE: global test value container to be mutated and checked across files, as needed

uses()
    ->beforeEach(function () {
        $this->baz = 0;
    })
    ->beforeAll(function () use ($globalHook) {
        $globalHook->beforeAll = 0;
    })
    ->afterEach(function () {
        $this->ith = 0;
    })
    ->afterAll(function () use ($globalHook) {
        $globalHook->afterAll = 0;
    })
    ->in('Hooks');
