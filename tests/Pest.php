<?php

uses()->group('integration')->in('Visual');

uses()
    ->beforeEach(function () {
        $this->baz = 0;
    })
    // ->beforeAll(function () {
    //     dump(0);
    // })
    ->afterEach(function () {
        $this->ith = 0;
    })
    // ->afterAll(function () {
    //     dump(0);
    // })
    ->in('Hooks');
