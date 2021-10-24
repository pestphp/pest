<?php

declare(strict_types=1);

namespace Pest\Emitters;

use Pest\Subscribers\EnsureTestsAreLoaded;
use PHPUnit\Event\Code;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Emitter;
use PHPUnit\Framework\Constraint;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\TestSuite;
use PHPUnit\TextUI\Configuration\Configuration;
use SebastianBergmann\GlobalState\Snapshot;

/**
 * @internal
 */
final class DispatchingEmitter implements Emitter
{
    /**
     * Creates a new Emitter instance.
     */
    public function __construct(private Emitter $baseEmitter)
    {
        // ..
    }

    public function eventFacadeSealed(): void
    {
        $this->baseEmitter->eventFacadeSealed(...func_get_args());
    }

    public function testRunnerStarted(): void
    {
        $this->baseEmitter->testRunnerStarted(...func_get_args());
    }

    public function testRunnerConfigured(Configuration $configuration): void
    {
        $this->baseEmitter->testRunnerConfigured($configuration);
    }

    public function testRunnerFinished(): void
    {
        $this->baseEmitter->testRunnerFinished(...func_get_args());
    }

    public function assertionMade(mixed $value, Constraint\Constraint $constraint, string $message, bool $hasFailed): void
    {
        $this->baseEmitter->assertionMade($value, $constraint, $message, $hasFailed);
    }

    public function bootstrapFinished(string $filename): void
    {
        $this->baseEmitter->bootstrapFinished($filename);
    }

    public function comparatorRegistered(string $className): void
    {
        $this->baseEmitter->comparatorRegistered($className);
    }

    public function extensionLoaded(string $name, string $version): void
    {
        $this->baseEmitter->extensionLoaded($name, $version);
    }

    public function globalStateCaptured(Snapshot $snapshot): void
    {
        $this->baseEmitter->globalStateCaptured($snapshot);
    }

    public function globalStateModified(Snapshot $snapshotBefore, Snapshot $snapshotAfter, string $diff): void
    {
        $this->baseEmitter->globalStateModified($snapshotBefore, $snapshotAfter, $diff);
    }

    public function globalStateRestored(Snapshot $snapshot): void
    {
        $this->baseEmitter->globalStateRestored($snapshot);
    }

    public function testErrored(Code\Test $test, Throwable $throwable): void
    {
        $this->baseEmitter->testErrored(...func_get_args());
    }

    public function testFailed(Code\Test $test, Throwable $throwable): void
    {
        $this->baseEmitter->testFailed(...func_get_args());
    }

    public function testFinished(Code\Test $test): void
    {
        $this->baseEmitter->testFinished(...func_get_args());
    }

    public function testOutputPrinted(Code\Test $test, string $output): void
    {
        $this->baseEmitter->testOutputPrinted(...func_get_args());
    }

    public function testPassed(Code\Test $test): void
    {
        $this->baseEmitter->testPassed(...func_get_args());
    }

    public function testPassedWithWarning(Code\Test $test, Throwable $throwable): void
    {
        $this->baseEmitter->testPassedWithWarning(...func_get_args());
    }

    public function testConsideredRisky(Code\Test $test, Throwable $throwable): void
    {
        $this->baseEmitter->testConsideredRisky(...func_get_args());
    }

    public function testAborted(Code\Test $test, Throwable $throwable): void
    {
        $this->baseEmitter->testAborted(...func_get_args());
    }

    public function testSkipped(Code\Test $test, string $message): void
    {
        $this->baseEmitter->testSkipped(...func_get_args());
    }

    public function testPrepared(Code\Test $test): void
    {
        $this->baseEmitter->testPrepared(...func_get_args());
    }

    public function testAfterTestMethodFinished(string $testClassName, Code\ClassMethod ...$calledMethods): void
    {
        $this->baseEmitter->testAfterTestMethodFinished(...func_get_args());
    }

    public function testAfterLastTestMethodFinished(string $testClassName, Code\ClassMethod ...$calledMethods): void
    {
        $this->baseEmitter->testAfterLastTestMethodFinished(...func_get_args());
    }

    public function testBeforeFirstTestMethodCalled(string $testClassName, Code\ClassMethod $calledMethod): void
    {
        $this->baseEmitter->testBeforeFirstTestMethodCalled(...func_get_args());
    }

    public function testBeforeFirstTestMethodFinished(string $testClassName, Code\ClassMethod ...$calledMethods): void
    {
        $this->baseEmitter->testBeforeFirstTestMethodFinished(...func_get_args());
    }

    public function testBeforeTestMethodCalled(string $testClassName, Code\ClassMethod $calledMethod): void
    {
        $this->baseEmitter->testBeforeTestMethodCalled(...func_get_args());
    }

    public function testBeforeTestMethodFinished(string $testClassName, Code\ClassMethod ...$calledMethods): void
    {
        $this->baseEmitter->testBeforeTestMethodFinished(...func_get_args());
    }

    public function testPreConditionCalled(string $testClassName, Code\ClassMethod $calledMethod): void
    {
        $this->baseEmitter->testPreConditionCalled(...func_get_args());
    }

    public function testPreConditionFinished(string $testClassName, Code\ClassMethod ...$calledMethods): void
    {
        $this->baseEmitter->testPreConditionFinished(...func_get_args());
    }

    public function testPostConditionCalled(string $testClassName, Code\ClassMethod $calledMethod): void
    {
        $this->baseEmitter->testPostConditionCalled(...func_get_args());
    }

    public function testPostConditionFinished(string $testClassName, Code\ClassMethod ...$calledMethods): void
    {
        $this->baseEmitter->testPostConditionFinished(...func_get_args());
    }

    public function testAfterTestMethodCalled(string $testClassName, Code\ClassMethod $calledMethod): void
    {
        $this->baseEmitter->testAfterTestMethodCalled(...func_get_args());
    }

    public function testAfterLastTestMethodCalled(string $testClassName, Code\ClassMethod $calledMethod): void
    {
        $this->baseEmitter->testAfterLastTestMethodCalled(...func_get_args());
    }

    public function testMockObjectCreated(string $className): void
    {
        $this->baseEmitter->testMockObjectCreated(...func_get_args());
    }

    public function testMockObjectCreatedForTrait(string $traitName): void
    {
        $this->baseEmitter->testMockObjectCreatedForTrait(...func_get_args());
    }

    public function testMockObjectCreatedForAbstractClass(string $className): void
    {
        $this->baseEmitter->testMockObjectCreatedForAbstractClass(...func_get_args());
    }

    public function testMockObjectCreatedFromWsdl(string $wsdlFile, string $originalClassName, string $mockClassName, array $methods, bool $callOriginalConstructor, array $options): void
    {
        $this->baseEmitter->testMockObjectCreatedFromWsdl(...func_get_args());
    }

    public function testPartialMockObjectCreated(string $className, string ...$methodNames): void
    {
        $this->baseEmitter->testPartialMockObjectCreated(...func_get_args());
    }

    public function testTestProxyCreated(string $className, array $constructorArguments): void
    {
        $this->baseEmitter->testTestProxyCreated(...func_get_args());
    }

    public function testTestStubCreated(string $className): void
    {
        $this->baseEmitter->testTestStubCreated(...func_get_args());
    }

    public function testSuiteLoaded(TestSuite $testSuite): void
    {
        EnsureTestsAreLoaded::setTestSuite($testSuite);

        $this->baseEmitter->testSuiteLoaded(...func_get_args());
    }

    public function testSuiteSorted(int $executionOrder, int $executionOrderDefects, bool $resolveDependencies): void
    {
        $this->baseEmitter->testSuiteSorted(...func_get_args());
    }

    public function testSuiteStarted(TestSuite $testSuite): void
    {
        $this->baseEmitter->testSuiteStarted(...func_get_args());
    }

    public function testSuiteFinished(TestSuite $testSuite, TestResult $result): void
    {
        $this->baseEmitter->testSuiteFinished(...func_get_args());
    }
}
