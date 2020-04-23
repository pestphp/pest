<?php

use Pest\Actions\AddsTests;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\WarningTestCase;

$closure = function () {
};
$pestTestCase = new class() extends \PHPUnit\Framework\TestCase {
};

test('default php unit tests', function () {
    $testSuite = new TestSuite();

    $phpUnitTestCase = new class() extends PhpUnitTestCase {
    };
    $testSuite->addTest($phpUnitTestCase);
    assertCount(1, $testSuite->tests());

    AddsTests::to($testSuite, new \Pest\TestSuite(getcwd()));
    assertCount(1, $testSuite->tests());
});

it('removes warnings', function () use ($pestTestCase) {
    $testSuite = new TestSuite();
    $warningTestCase = new WarningTestCase('No tests found in class "Pest\TestCase".');
    $testSuite->addTest($warningTestCase);

    AddsTests::to($testSuite, new \Pest\TestSuite(getcwd()));
    assertCount(0, $testSuite->tests());
});
