<?php

use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\TestAlreadyExist;
use Pest\TestSuite;

it('does not allow to add the same test description twice', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $test = function () {};
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
    $this->expectException(TestAlreadyExist::class);
    $this->expectExceptionMessage(sprintf('A test with the description `%s` already exist in the filename `%s`.', 'foo', __FILE__));
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
});

it('alerts users about tests with arguments but no input', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $test = function (int $arg) {};
    $this->expectException(DatasetMissing::class);
    $this->expectExceptionMessage(sprintf("A test with the description '%s' has %d argument(s) ([%s]) and no dataset(s) provided in %s", 'foo', 1, 'int $arg', __FILE__));
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
});
