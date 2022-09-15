<?php

use Pest\Logging\JUnit;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;

beforeEach(function () {
    file_put_contents(__DIR__ . '/junit.html', '');
});

it('is can successfully call all public methods', function () {
    $junit = new JUnit(__DIR__ . '/junit.html');
    $junit->startTestSuite(new TestSuite());
    $junit->startTest($this);
    $junit->addError($this, new Exception(), 0);
    $junit->addFailure($this, new AssertionFailedError(), 0);
    $junit->addWarning($this, new Warning(), 0);
    $junit->addIncompleteTest($this, new Exception(), 0);
    $junit->addRiskyTest($this, new Exception(), 0);
    $junit->addSkippedTest($this, new Exception(), 0);
    $junit->endTest($this, 0);
    $junit->endTestSuite(new TestSuite());
    $this->expectNotToPerformAssertions();
})->skip('Not supported yet.');

afterEach(function () {
    unlink(__DIR__ . '/junit.html');
});
