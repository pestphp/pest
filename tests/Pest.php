<?php

use Tests\CustomTestCase\CustomTestCase;
use Tests\CustomTestCaseInSubFolders\SubFolder\SubFolder\CustomTestCaseInSubFolder;

error_reporting(E_ALL);

$GLOBALS['__PEST_INTERNAL_TEST_SUITE'] = true;

pest()->project()->github('pestphp/pest');

pest()->in('PHPUnit/CustomTestCaseInSubFolders/SubFolder/SubFolder')->use(CustomTestCaseInSubFolder::class);

// test case for all the directories inside PHPUnit/GlobPatternTests/SubFolder/
pest()->in('PHPUnit/GlobPatternTests/SubFolder/*')->extend(CustomTestCase::class);

// test case for all the files that end with AsPattern.php inside PHPUnit/GlobPatternTests/SubFolder2/
pest()->in('PHPUnit/GlobPatternTests/SubFolder2/*AsPattern.php')->use(CustomTestCase::class);

pest()->in('Visual')->group('integration');

// NOTE: global test value container to be mutated and checked across files, as needed
$_SERVER['globalHook'] = (object) ['calls' => (object) ['beforeAll' => 0, 'afterAll' => 0]];

pest()
    ->in('Hooks')
    ->beforeEach(function () {
        $this->baz = 0;
    })
    ->beforeAll(function () {
        $_SERVER['globalHook']->beforeAll = 0;
        $_SERVER['globalHook']->calls->beforeAll++;
    })
    ->afterEach(function () {
        if (! isset($this->ith)) {
            return;
        }

        assert($this->ith === 1, 'Expected $this->ith to be 1, but got '.$this->ith);
        $this->ith++;
    })
    ->afterAll(function () {
        $_SERVER['globalHook']->afterAll = 0;
        $_SERVER['globalHook']->calls->afterAll++;
    });

pest()->in('Hooks')
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
        if (! isset($this->ith)) {
            return;
        }

        assert($this->ith === 2, 'Expected $this->ith to be 1, but got '.$this->ith);
        $this->ith++;
    })
    ->afterAll(function () {
        expect($_SERVER['globalHook'])
            ->toHaveProperty('afterAll')
            ->and($_SERVER['globalHook']->afterAll)
            ->toBe(0);

        $_SERVER['globalHook']->afterAll = 1;
    });

function helper_returns_string()
{
    return 'string';
}

dataset('dataset_in_pest_file', ['A', 'B']);

function removeAnsiEscapeSequences(string $input): ?string
{
    return preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $input);
}
