<?php

use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\TestAlreadyExist;
use Pest\Exceptions\TestClosureMustNotBeStatic;
use Pest\Factories\TestCaseMethodFactory;
use Pest\TestSuite;

it('does not allow to add the same test description twice', function (): void {
    $testSuite = new TestSuite(getcwd(), 'tests');
    $method = new TestCaseMethodFactory('foo', null);
    $method->description = 'bar';

    $testSuite->tests->set($method);
    $testSuite->tests->set($method);
})->throws(
    TestAlreadyExist::class,
    sprintf('A test named [%s] already exists in [%s]. Please give this test a different description.', 'bar', 'foo'),
);

it('does not allow static closures', function (): void {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $method = new TestCaseMethodFactory('foo', static function (): void {});
    $method->description = 'bar';

    $testSuite->tests->set($method);
})->throws(
    TestClosureMustNotBeStatic::class,
    'Test closures may not be static. Please remove the [static] keyword from the test [bar] in [foo].',
);

it('alerts users about tests with arguments but no input', function (): void {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $method = new TestCaseMethodFactory('foo', function (int $arg): void {});

    $method->description = 'bar';

    $testSuite->tests->set($method);
})->throws(
    DatasetMissing::class,
    sprintf('The test [%s] in [%s] expects [%d] argument(s) ([%s]), but no dataset was provided. Please chain [with()] onto the test to supply one.', 'bar', 'foo', 1, 'int $arg'),
);

it('can return an array of all test suite filenames', function (): void {
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
