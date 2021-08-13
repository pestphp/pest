<?php

use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\TestAlreadyExist;
use Pest\TestSuite;

it('does not allow to add the same test description twice', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $test = function () {};
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
})->throws(
    TestAlreadyExist::class,
    sprintf('A test with the description `%s` already exist in the filename `%s`.', 'foo', __FILE__),
);

it('alerts users about tests with arguments but no input', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $test = function (int $arg) {};
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
})->throws(
    DatasetMissing::class,
    sprintf("A test with the description '%s' has %d argument(s) ([%s]) and no dataset(s) provided in %s", 'foo', 1, 'int $arg', __FILE__),
);

it('can return an array of all test suite filenames', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $test = function () {};
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'bar', $test));

    expect($testSuite->tests->getFilenames())->toEqual([
        __FILE__,
        __FILE__,
    ]);
});
