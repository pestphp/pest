<?php

use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\TestAlreadyExist;
use Pest\Exceptions\TestClosureMustNotBeStatic;
use Pest\Factories\TestCaseMethodFactory;
use Pest\TestSuite;

it('does not allow to add the same test description twice', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $method = new TestCaseMethodFactory('foo', null);
    $method->description = 'bar';

    $testSuite->tests->set($method);
    $testSuite->tests->set($method);
})->throws(
    TestAlreadyExist::class,
    sprintf('A test with the description `%s` already exists in the filename `%s`.', 'bar', 'foo'),
);

it('does not allow static closures', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $method = new TestCaseMethodFactory('foo', static function () {});
    $method->description = 'bar';

    $testSuite->tests->set($method);
})->throws(
    TestClosureMustNotBeStatic::class,
    'Test closure must not be static. Please remove the `static` keyword from the `bar` method in `foo`.',
);

it('alerts users about tests with arguments but no input', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $method = new TestCaseMethodFactory('foo', function (int $arg) {});

    $method->description = 'bar';

    $testSuite->tests->set($method);
})->throws(
    DatasetMissing::class,
    sprintf("A test with the description '%s' has %d argument(s) ([%s]) and no dataset(s) provided in %s", 'bar', 1, 'int $arg', 'foo'),
);

it('can return an array of all test suite filenames', function () {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $method = new TestCaseMethodFactory('a', null);
    $method->description = 'b';
    $testSuite->tests->set($method);

    $method = new TestCaseMethodFactory('c', null);
    $method->description = 'd';
    $testSuite->tests->set($method);

    expect($testSuite->tests->getFilenames())->toEqual([
        'a',
        'c',
    ]);
});
