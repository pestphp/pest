<?php

use Tests\CustomTestCaseInSubFolders\SubFolder\SubFolder\CustomTestCaseInSubFolder;

uses(CustomTestCaseInSubFolder::class)->in('PHPUnit/CustomTestCaseInSubFolders/SubFolder/SubFolder');

uses()->group('integration')->in('Visual');

// NOTE: global test value container to be mutated and checked across files, as needed
$globalHook = (object) ['calls' => (object) ['beforeAll' => 0, 'afterAll' => 0]];

uses()
    ->beforeEach(function () {
        $this->baz = 0;
    })
    ->beforeAll(function () use ($globalHook) {
        $globalHook->beforeAll = 0;
        $globalHook->calls->beforeAll++;
    })
    ->afterEach(function () {
        $this->ith = 0;
    })
    ->afterAll(function () use ($globalHook) {
        $globalHook->afterAll = 0;
        $globalHook->calls->afterAll++;
    })
    ->in('Hooks');
