<?php

use Tests\CustomTestCase\CustomTestCase;
use Tests\CustomTestCaseInSubFolders\SubFolder\SubFolder\CustomTestCaseInSubFolder;

uses(CustomTestCaseInSubFolder::class)->in('PHPUnit/CustomTestCaseInSubFolders/SubFolder/SubFolder');

// test case for all the directories inside PHPUnit/GlobPatternTests/SubFolder/
uses(CustomTestCase::class)->in('PHPUnit/GlobPatternTests/SubFolder/*/');

// test case for all the files that end with AsPattern.php inside PHPUnit/GlobPatternTests/SubFolder2/
uses(CustomTestCase::class)->in('PHPUnit/GlobPatternTests/SubFolder2/*AsPattern.php');

uses()->group('integration')->in('Visual');

// NOTE: global test value container to be mutated and checked across files, as needed
$_SERVER['globalHook'] = (object) ['calls' => (object) ['beforeAll' => 0, 'afterAll' => 0]];

uses()
    ->beforeEach(function () {
        $this->baz = 0;
    })
    ->beforeAll(function () {
        $_SERVER['globalHook']->beforeAll = 0;
        $_SERVER['globalHook']->calls->beforeAll++;
    })
    ->afterEach(function () {
        $this->ith = 0;
    })
    ->afterAll(function () {
        $_SERVER['globalHook']->afterAll = 0;
        $_SERVER['globalHook']->calls->afterAll++;
    })
    ->in('Hooks');

function helper_returns_string()
{
    return 'string';
}

dataset('dataset_in_pest_file', ['A', 'B']);
