<?php

use Tests\CustomTestCase\CustomTestCase;
use Tests\CustomTestCaseInSubFolders\SubFolder\SubFolder\CustomTestCaseInSubFolder;

uses(CustomTestCaseInSubFolder::class)->in('PHPUnit/CustomTestCaseInSubFolders/SubFolder/SubFolder');

uses(CustomTestCase::class)->in('PHPUnit/CustomTestCase/SubFolder/*/');

uses(CustomTestCase::class)->in('PHPUnit/CustomTestCase/SubFolder2/*AsPattern.php');

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
