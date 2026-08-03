<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ResultCollector;
use Pest\Subscribers\EnsureTiaAssertionsAreRecordedOnFinished;
use Pest\Subscribers\EnsureTiaResultsAreCollected;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Telemetry\Duration;
use PHPUnit\Event\Telemetry\Info;
use PHPUnit\Event\Telemetry\MemoryUsage;
use PHPUnit\Event\Telemetry\System;
use PHPUnit\Event\Telemetry\SystemCpuTimeMeter;
use PHPUnit\Event\Telemetry\SystemGarbageCollectorStatusProvider;
use PHPUnit\Event\Telemetry\SystemMemoryMeter;
use PHPUnit\Event\Telemetry\SystemStopWatch;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\TestData\DataFromDataProvider;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Metadata\MetadataCollection;

function tiaResultKeyTestMethod(?string $dataSetName): TestMethod
{
    $testData = $dataSetName === null
        ? TestDataCollection::fromArray([])
        : TestDataCollection::fromArray([DataFromDataProvider::from($dataSetName, '', '')]);

    return new TestMethod(
        'Tests\Feature\OrderTest',
        'it prices an order',
        '/project/tests/Feature/OrderTest.php',
        1,
        new TestDox('Order', 'it prices an order', 'it prices an order'),
        MetadataCollection::fromArray([]),
        $testData,
    );
}

function tiaResultKeyTelemetryInfo(): Info
{
    $system = new System(
        new SystemStopWatch,
        new SystemMemoryMeter,
        new SystemGarbageCollectorStatusProvider,
        new SystemCpuTimeMeter,
    );

    $zeroDuration = Duration::fromSecondsAndNanoseconds(0, 0);
    $zeroMemory = MemoryUsage::fromBytes(0);
    $zeroCpuTime = $system->snapshot()->userCpuTime();

    return new Info(
        $system->snapshot(),
        $zeroDuration,
        $zeroMemory,
        $zeroDuration,
        $zeroMemory,
        $zeroCpuTime,
        $zeroCpuTime,
        $zeroCpuTime,
        $zeroCpuTime,
        $zeroCpuTime,
        $zeroCpuTime,
    );
}

it('keys a result per dataset row rather than per method', function (): void {
    $collector = new ResultCollector;
    $subscriber = new EnsureTiaResultsAreCollected($collector);

    $subscriber->notify(new PreparationStarted(tiaResultKeyTelemetryInfo(), tiaResultKeyTestMethod('opp')));
    $collector->testPassed();
    $collector->finishTest();

    $subscriber->notify(new PreparationStarted(tiaResultKeyTelemetryInfo(), tiaResultKeyTestMethod('fake')));
    $collector->testSkipped('the fake driver does not report balances');
    $collector->finishTest();

    // Without the dataset in the key both rows write to `Class::method`, so the
    // second one overwrites the first and a replay hands every row the same
    // status — a passing row reported as skipped, or a failing one as passed.
    expect(array_keys($collector->all()))->toBe([
        'Tests\Feature\OrderTest::it prices an order#opp',
        'Tests\Feature\OrderTest::it prices an order#fake',
    ]);
});

it('records assertions against the same per-dataset key', function (): void {
    $collector = new ResultCollector;

    (new EnsureTiaResultsAreCollected($collector))->notify(
        new PreparationStarted(tiaResultKeyTelemetryInfo(), tiaResultKeyTestMethod('opp')),
    );
    $collector->testPassed();

    (new EnsureTiaAssertionsAreRecordedOnFinished($collector))->notify(
        new Finished(tiaResultKeyTelemetryInfo(), tiaResultKeyTestMethod('opp'), 7),
    );

    expect($collector->all()['Tests\Feature\OrderTest::it prices an order#opp']['assertions'])->toBe(7);
});

it('leaves a test without a dataset keyed by class and method', function (): void {
    $collector = new ResultCollector;

    (new EnsureTiaResultsAreCollected($collector))->notify(
        new PreparationStarted(tiaResultKeyTelemetryInfo(), tiaResultKeyTestMethod(null)),
    );
    $collector->testPassed();

    expect(array_keys($collector->all()))->toBe(['Tests\Feature\OrderTest::it prices an order']);
});
