<?php

use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\TestAlreadyExist;
use Pest\Factories\TestCaseMethodFactory;
use Pest\TestSuite;

it('does not allow to add the same test description twice', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $method = new TestCaseMethodFactory('foo', 'bar', null);

    $testSuite->tests->set($method);
    $testSuite->tests->set($method);
})->throws(
    TestAlreadyExist::class,
    sprintf('A test with the description `%s` already exists in the filename `%s`.', 'bar', 'foo'),
);

it('alerts users about tests with arguments but no input', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $method = new TestCaseMethodFactory('foo', 'bar', function (int $arg) {});

    $testSuite->tests->set($method);
})->throws(
    DatasetMissing::class,
    sprintf("A test with the description '%s' has %d argument(s) ([%s]) and no dataset(s) provided in %s", 'bar', 1, 'int $arg', 'foo'),
);

it('can return an array of all test suite filenames', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $testSuite->tests->set(new TestCaseMethodFactory('a', 'b', null));
    $testSuite->tests->set(new TestCaseMethodFactory('c', 'd', null));

    expect($testSuite->tests->getFilenames())->toEqual([
        'a',
        'c',
    ]);
});
