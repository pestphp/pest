<?php

use Pest\Logging\TeamCity;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\TextUI\DefaultResultPrinter;

beforeEach(function () {
    file_put_contents(__DIR__ . '/output.txt', '');
});

it('is can successfully call all public methods', function () {
    $teamCity = new TeamCity(__DIR__ . '/output.txt', false, DefaultResultPrinter::COLOR_ALWAYS);
    expect($teamCity::isPestTest($this))->toBeTrue();
    $teamCity->startTestSuite(new TestSuite());
    $teamCity->startTest($this);
    $teamCity->addError($this, new Exception('Don\'t worry about this error. Its purposeful.'), 0);
    $teamCity->addFailure($this, new AssertionFailedError('Don\'t worry about this error. Its purposeful.'), 0);
    $teamCity->addWarning($this, new Warning(), 0);
    $teamCity->addIncompleteTest($this, new Exception(), 0);
    $teamCity->addRiskyTest($this, new Exception(), 0);
    $teamCity->addSkippedTest($this, new Exception(), 0);
    $teamCity->endTest($this, 0);
    $teamCity->printResult(new TestResult());
    $teamCity->endTestSuite(new TestSuite());
});

afterEach(function () {
    unlink(__DIR__ . '/output.txt');
});
