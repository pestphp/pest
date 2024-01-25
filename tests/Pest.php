<?php

use Tests\CustomTestCase\CustomTestCase;
use Tests\CustomTestCaseInSubFolders\SubFolder\SubFolder\CustomTestCaseInSubFolder;

$GLOBALS['__PEST_INTERNAL_TEST_SUITE'] = true;

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

uses()
    ->beforeEach(function () {
        expect($this)
            ->toHaveProperty('baz')
            ->and($this->baz)
            ->toBe(0);

        $this->baz = 1;
    })
    ->beforeAll(function () {
        expect($_SERVER['globalHook'])
            ->toHaveProperty('beforeAll')
            ->and($_SERVER['globalHook']->beforeAll)
            ->toBe(0);

        $_SERVER['globalHook']->beforeAll = 1;
    })
    ->afterEach(function () {
        expect($this)
            ->toHaveProperty('ith')
            ->and($this->ith)
            ->toBe(0);

        $this->ith = 1;
    })
    ->afterAll(function () {
        expect($_SERVER['globalHook'])
            ->toHaveProperty('afterAll')
            ->and($_SERVER['globalHook']->afterAll)
            ->toBe(0);

        $_SERVER['globalHook']->afterAll = 1;
    })
    ->in('Hooks');

function helper_returns_string()
{
    return 'string';
}

dataset('dataset_in_pest_file', ['A', 'B']);

function removeAnsiEscapeSequences(string $input): ?string
{
    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $input);
}
