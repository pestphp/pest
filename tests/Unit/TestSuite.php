<?php

use Pest\Exceptions\DatasetMissing;
use Pest\Exceptions\ShouldNotHappen;
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

it('can look up registered test methods by evaluable name', function (): void {
    $testSuite = new TestSuite(getcwd(), 'tests');

    $firstMethod = new TestCaseMethodFactory('foo', null);
    $firstMethod->description = 'first test_with punctuation!';
    $testSuite->tests->set($firstMethod);

    $secondMethod = new TestCaseMethodFactory('foo', null);
    $secondMethod->description = 'second test';
    $testSuite->tests->set($secondMethod);

    $collidingMethod = new TestCaseMethodFactory('foo', null);
    $collidingMethod->description = 'second-test';
    $testSuite->tests->set($collidingMethod);

    $testCase = $testSuite->tests->get('foo');

    expect($testCase->hasMethod('__pest_evaluable_first_test__with_punctuation_'))->toBeTrue()
        ->and($testCase->getMethod('__pest_evaluable_first_test__with_punctuation_'))->toBe($firstMethod)
        ->and($testCase->hasMethod('__pest_evaluable_second_test'))->toBeTrue()
        ->and($testCase->getMethod('__pest_evaluable_second_test'))->toBe($secondMethod)
        ->and($testCase->hasMethod('__pest_evaluable_missing'))->toBeFalse()
        ->and(fn () => $testCase->getMethod('__pest_evaluable_missing'))->toThrow(ShouldNotHappen::class, 'Method [__pest_evaluable_missing] not found.');
});

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
