<?php

use Pest\Exceptions\TestAlreadyExist;
use Pest\TestSuite;

it('does not allow to add the same test description twice', function () {
    $testSuite = new TestSuite(getcwd());
    $test = fn () => '';
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
    $this->expectException(TestAlreadyExist::class);
    $this->expectExceptionMessage(sprintf('A test with the description `%s` already exist in the filename `%s`.', 'foo', __FILE__));
    $testSuite->tests->set(new \Pest\Factories\TestCaseFactory(__FILE__, 'foo', $test));
});
