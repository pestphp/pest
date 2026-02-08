<?php

use Pest\Support\StateGenerator;
use PHPUnit\Event\Code\ClassMethod;
use PHPUnit\Event\Code\ThrowableBuilder;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\GarbageCollectorStatus;
use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\Snapshot;
use PHPUnit\Event\Test\AfterLastTestMethodErrored;
use PHPUnit\Event\Test\BeforeFirstTestMethodErrored;
use PHPUnit\TestRunner\TestResult\TestResult as PHPUnitTestResult;

function makeTelemetryInfo(): Info
{
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
}

function makePHPUnitTestResult(array $erroredEvents): PHPUnitTestResult
{
    return new PHPUnitTestResult(
        0, 0, 0,
        $erroredEvents,
        [], [], [], [], [], [], [], [], [], [], [], [], [], [], [], [], [], [], [], 0,
    );
}

it('handles AfterLastTestMethodErrored without TypeError', function (): void {
    $generator = new StateGenerator;

    $throwable = ThrowableBuilder::from(new RuntimeException('Teardown error'));

    $afterLastEvent = new AfterLastTestMethodErrored(
        makeTelemetryInfo(),
        \PHPUnit\Framework\TestCase::class,
        new ClassMethod(\PHPUnit\Framework\TestCase::class, 'tearDown'),
        $throwable,
    );

    $phpunitResult = makePHPUnitTestResult([$afterLastEvent]);

    // Before the fix, this would throw:
    // TypeError: TestResult::fromBeforeFirstTestMethodErrored(): Argument #1 must be of type
    // BeforeFirstTestMethodErrored, AfterLastTestMethodErrored given
    $state = $generator->fromPhpUnitTestResult(0, $phpunitResult);

    // The AfterLastTestMethodErrored event should be processed and added to state
    expect($state->suiteTests)->toHaveCount(1);
});

it('handles BeforeFirstTestMethodErrored correctly', function (): void {
    $generator = new StateGenerator;

    $throwable = ThrowableBuilder::from(new RuntimeException('Setup error'));

    $beforeFirstEvent = new BeforeFirstTestMethodErrored(
        makeTelemetryInfo(),
        \PHPUnit\Framework\TestCase::class,
        new ClassMethod(\PHPUnit\Framework\TestCase::class, 'setUp'),
        $throwable,
    );

    $phpunitResult = makePHPUnitTestResult([$beforeFirstEvent]);
    $state = $generator->fromPhpUnitTestResult(0, $phpunitResult);

    expect($state->suiteTests)->toHaveCount(1);
});

it('handles mixed errored events without TypeError', function (): void {
    $generator = new StateGenerator;

    $throwable = ThrowableBuilder::from(new RuntimeException('Error'));

    $beforeEvent = new BeforeFirstTestMethodErrored(
        makeTelemetryInfo(),
        \PHPUnit\Framework\TestCase::class,
        new ClassMethod(\PHPUnit\Framework\TestCase::class, 'setUp'),
        $throwable,
    );

    $afterEvent = new AfterLastTestMethodErrored(
        makeTelemetryInfo(),
        \PHPUnit\Framework\TestCase::class,
        new ClassMethod(\PHPUnit\Framework\TestCase::class, 'tearDown'),
        $throwable,
    );

    $phpunitResult = makePHPUnitTestResult([$beforeEvent, $afterEvent]);
    $state = $generator->fromPhpUnitTestResult(0, $phpunitResult);

    // Both events share the same testClassName key, so the second overwrites the first
    expect($state->suiteTests)->toHaveCount(1);
});
