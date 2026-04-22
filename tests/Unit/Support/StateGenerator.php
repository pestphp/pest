<?php

use NunoMaduro\Collision\Adapters\Phpunit\TestResult as CollisionTestResult;
use Pest\Support\StateGenerator;
use PHPUnit\Event\Code\TestCollection;
use PHPUnit\Event\Code\TestDoxBuilder;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Event\TestRunner\DeprecationTriggered as RunnerDeprecationTriggered;
use PHPUnit\Event\TestRunner\NoticeTriggered as RunnerNoticeTriggered;
use PHPUnit\Event\TestRunner\WarningTriggered as RunnerWarningTriggered;
use PHPUnit\Event\TestSuite\Skipped as TestSuiteSkipped;
use PHPUnit\Event\TestSuite\TestSuiteWithName;
use PHPUnit\Metadata\MetadataCollection;
use PHPUnit\TestRunner\TestResult\Issues\Issue;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;

$telemetryInfo = function (): Info {
    return new Info(
        new Snapshot(
            HRTime::fromSecondsAndNanoseconds(0, 0),
            MemoryUsage::fromBytes(0),
            MemoryUsage::fromBytes(0),
            new GarbageCollectorStatus(0, 0, 0, 0, 0.0, 0.0, 0.0, 0.0, false, false, false, 0),
        ),
        Duration::fromSecondsAndNanoseconds(0, 0),
        MemoryUsage::fromBytes(0),
        Duration::fromSecondsAndNanoseconds(0, 0),
        MemoryUsage::fromBytes(0),
    );
};

$syntheticTest = function (string $className = 'AcmeTest', string $methodName = 'testSomething'): TestMethod {
    return new TestMethod(
        $className, // @phpstan-ignore-line
        $methodName,
        __FILE__,
        1,
        TestDoxBuilder::fromClassNameAndMethodName($className, $methodName), // @phpstan-ignore-line
        MetadataCollection::fromArray([]),
        TestDataCollection::fromArray([]),
    );
};

$phpUnitTestResult = function (array $overrides = []): PHPUnitTestResult {
    $defaults = [
        'testErroredEvents' => [],
        'testFailedEvents' => [],
        'testConsideredRiskyEvents' => [],
        'testSuiteSkippedEvents' => [],
        'testSkippedEvents' => [],
        'testMarkedIncompleteEvents' => [],
        'testTriggeredPhpunitDeprecationEvents' => [],
        'testTriggeredPhpunitErrorEvents' => [],
        'testTriggeredPhpunitNoticeEvents' => [],
        'testTriggeredPhpunitWarningEvents' => [],
        'testRunnerTriggeredDeprecationEvents' => [],
        'testRunnerTriggeredNoticeEvents' => [],
        'testRunnerTriggeredWarningEvents' => [],
        'errors' => [],
        'deprecations' => [],
        'notices' => [],
        'warnings' => [],
        'phpDeprecations' => [],
        'phpNotices' => [],
        'phpWarnings' => [],
    ];

    $values = array_merge($defaults, $overrides);

    return new PHPUnitTestResult(
        0,
        0,
        0,
        $values['testErroredEvents'],
        $values['testFailedEvents'],
        $values['testConsideredRiskyEvents'],
        $values['testSuiteSkippedEvents'],
        $values['testSkippedEvents'],
        $values['testMarkedIncompleteEvents'],
        $values['testTriggeredPhpunitDeprecationEvents'],
        $values['testTriggeredPhpunitErrorEvents'],
        $values['testTriggeredPhpunitNoticeEvents'],
        $values['testTriggeredPhpunitWarningEvents'],
        $values['testRunnerTriggeredDeprecationEvents'],
        $values['testRunnerTriggeredNoticeEvents'],
        $values['testRunnerTriggeredWarningEvents'],
        $values['errors'],
        $values['deprecations'],
        $values['notices'],
        $values['warnings'],
        $values['phpDeprecations'],
        $values['phpNotices'],
        $values['phpWarnings'],
        0,
    );
};

it('records errors as failed results', function () use ($phpUnitTestResult, $syntheticTest) {
    $issue = Issue::from('/tmp/test.php', 1, 'user error description', $syntheticTest());

    $state = (new StateGenerator)->fromPhpUnitTestResult(0, $phpUnitTestResult(['errors' => [$issue]]));

    expect($state->suiteTests)->toHaveCount(1);

    $result = array_values($state->suiteTests)[0];

    expect($result->type)->toBe(CollisionTestResult::FAIL)
        ->and($result->throwable?->message())->toBe('user error description');
});

it('records test runner triggered deprecation events', function () use ($phpUnitTestResult, $telemetryInfo) {
    $event = new RunnerDeprecationTriggered($telemetryInfo(), 'cli flag is deprecated');

    $state = (new StateGenerator)->fromPhpUnitTestResult(0, $phpUnitTestResult([
        'testRunnerTriggeredDeprecationEvents' => [$event],
    ]));

    expect($state->suiteTests)->toHaveCount(1);

    $result = array_values($state->suiteTests)[0];

    expect($result->type)->toBe(CollisionTestResult::DEPRECATED)
        ->and($result->testCaseName)->toBe('PHPUnit test runner deprecation')
        ->and($result->throwable?->message())->toBe('cli flag is deprecated');
});

it('records test runner triggered notice events', function () use ($phpUnitTestResult, $telemetryInfo) {
    $event = new RunnerNoticeTriggered($telemetryInfo(), 'runner notice');

    $state = (new StateGenerator)->fromPhpUnitTestResult(0, $phpUnitTestResult([
        'testRunnerTriggeredNoticeEvents' => [$event],
    ]));

    expect($state->suiteTests)->toHaveCount(1);

    $result = array_values($state->suiteTests)[0];

    expect($result->type)->toBe(CollisionTestResult::NOTICE)
        ->and($result->testCaseName)->toBe('PHPUnit test runner notice')
        ->and($result->throwable?->message())->toBe('runner notice');
});

it('records test runner triggered warning events', function () use ($phpUnitTestResult, $telemetryInfo) {
    $event = new RunnerWarningTriggered($telemetryInfo(), 'runner warning');

    $state = (new StateGenerator)->fromPhpUnitTestResult(0, $phpUnitTestResult([
        'testRunnerTriggeredWarningEvents' => [$event],
    ]));

    expect($state->suiteTests)->toHaveCount(1);

    $result = array_values($state->suiteTests)[0];

    expect($result->type)->toBe(CollisionTestResult::WARN)
        ->and($result->testCaseName)->toBe('PHPUnit test runner warning')
        ->and($result->throwable?->message())->toBe('runner warning');
});

it('records test suite skipped events', function () use ($phpUnitTestResult, $telemetryInfo) {
    $suite = new TestSuiteWithName('AcmeSuite', 3, TestCollection::fromArray([]));
    $event = new TestSuiteSkipped($telemetryInfo(), $suite, 'requires PHP 9');

    $state = (new StateGenerator)->fromPhpUnitTestResult(0, $phpUnitTestResult([
        'testSuiteSkippedEvents' => [$event],
    ]));

    expect($state->suiteTests)->toHaveCount(1);

    $result = array_values($state->suiteTests)[0];

    expect($result->type)->toBe(CollisionTestResult::SKIPPED)
        ->and($result->testCaseName)->toBe('AcmeSuite')
        ->and($result->throwable?->message())->toBe('requires PHP 9');
});

it('keeps multiple standalone events as distinct entries', function () use ($phpUnitTestResult, $telemetryInfo) {
    $state = (new StateGenerator)->fromPhpUnitTestResult(0, $phpUnitTestResult([
        'testRunnerTriggeredDeprecationEvents' => [
            new RunnerDeprecationTriggered($telemetryInfo(), 'first'),
            new RunnerDeprecationTriggered($telemetryInfo(), 'second'),
        ],
        'testRunnerTriggeredWarningEvents' => [
            new RunnerWarningTriggered($telemetryInfo(), 'third'),
        ],
    ]));

    expect($state->suiteTests)->toHaveCount(3);
});
