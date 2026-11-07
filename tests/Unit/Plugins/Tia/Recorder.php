<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Recorder;

describe('activateLinkTracking()', function (): void {
    it('tracks tables without a coverage driver', function (): void {
        $recorder = new Recorder;
        $recorder->activateLinkTracking();

        $recorder->beginTest('Some\Missing\TestClass', 'it does things', '/project/tests/Feature/OrderTest.php');
        $recorder->linkTable('orders');
        $recorder->linkTable('users');
        $recorder->endTest();

        expect($recorder->perTestTables())
            ->toBe(['/project/tests/Feature/OrderTest.php' => ['orders', 'users']]);
    });

    it('tracks inertia components without a coverage driver', function (): void {
        $recorder = new Recorder;
        $recorder->activateLinkTracking();

        $recorder->beginTest('Some\Missing\TestClass', 'it renders', '/project/tests/Feature/DashboardTest.php');
        $recorder->linkInertiaComponent('Dashboard/Index');
        $recorder->endTest();

        expect($recorder->perTestInertiaComponents())
            ->toBe(['/project/tests/Feature/DashboardTest.php' => ['Dashboard/Index']]);
    });

    it('tracks linked sources across consecutive tests', function (): void {
        $recorder = new Recorder;
        $recorder->activateLinkTracking();

        $recorder->beginTest('Some\Missing\TestClass', 'first', '/project/tests/Feature/FirstTest.php');
        $recorder->linkSource('/project/resources/views/welcome.blade.php');
        $recorder->endTest();

        $recorder->beginTest('Some\Missing\TestClass', 'second', '/project/tests/Feature/SecondTest.php');
        $recorder->linkSource('/project/resources/views/about.blade.php');
        $recorder->endTest();

        expect($recorder->perTestFiles())->toBe([
            '/project/tests/Feature/FirstTest.php' => ['/project/resources/views/welcome.blade.php'],
            '/project/tests/Feature/SecondTest.php' => ['/project/resources/views/about.blade.php'],
        ]);
    });

    it('records nothing while inactive', function (): void {
        $recorder = new Recorder;

        $recorder->beginTest('Some\Missing\TestClass', 'it does things', '/project/tests/Feature/OrderTest.php');
        $recorder->linkTable('orders');
        $recorder->endTest();

        expect($recorder->perTestTables())->toBeEmpty();
    });
});

describe('warmupUsing()', function (): void {
    /*
     * The driver must not only exist — it must be able to instrument the
     * fixture files (pcov only instruments files under pcov.directory).
     */
    $driverCanInstrumentFixtures = function (): bool {
        $fixture = dirname(__DIR__, 3).'/Fixtures/Tia/WarmupExecutedSource.php';
        require_once $fixture;

        if (function_exists('pcov\start')) {
            \pcov\clear();
            \pcov\start();
            tia_warmup_executed_source();
            \pcov\stop();

            $collected = \pcov\collect(\pcov\inclusive, [realpath($fixture) ?: $fixture]);

            return $collected !== [];
        }

        return function_exists('xdebug_start_code_coverage') && function_exists('xdebug_info') && in_array('coverage', (array) xdebug_info('mode'), true);
    };

    beforeEach(function (): void {
        require_once dirname(__DIR__, 3).'/Fixtures/Tia/WarmupExecutedSource.php';
        require_once dirname(__DIR__, 3).'/Fixtures/Tia/TestOnlySource.php';
        require_once dirname(__DIR__, 3).'/Fixtures/Tia/SharedSource.php';
    });

    it('excludes files whose executed lines are fully covered by the warm-up baseline', function (): void {
        $recorder = new Recorder;
        $recorder->warmupUsing(function (): void {
            tia_warmup_executed_source();
        });
        $recorder->activate();

        $recorder->beginTest('Some\Missing\TestClass', 'boot noise', '/project/tests/Feature/BootNoiseTest.php');
        tia_warmup_executed_source();
        tia_test_only_source();
        $recorder->endTest();

        $files = array_map(basename(...), $recorder->perTestFiles()['/project/tests/Feature/BootNoiseTest.php'] ?? []);

        expect($files)->toContain('TestOnlySource.php')
            ->not->toContain('WarmupExecutedSource.php');
    })->skipOnWindows()->skip(fn (): bool => ! $driverCanInstrumentFixtures(), 'requires pcov (with pcov.directory covering the test fixtures) or xdebug (coverage mode)');

    it('keeps files where a test executes lines beyond the warm-up baseline', function (): void {
        $recorder = new Recorder;
        $recorder->warmupUsing(function (): void {
            tia_shared_source_boot_path();
        });
        $recorder->activate();

        $recorder->beginTest('Some\Missing\TestClass', 'beyond baseline', '/project/tests/Feature/BeyondBaselineTest.php');
        tia_shared_source_boot_path();
        tia_shared_source_test_path();
        $recorder->endTest();

        $files = array_map(basename(...), $recorder->perTestFiles()['/project/tests/Feature/BeyondBaselineTest.php'] ?? []);

        expect($files)->toContain('SharedSource.php');
    })->skipOnWindows()->skip(fn (): bool => ! $driverCanInstrumentFixtures(), 'requires pcov (with pcov.directory covering the test fixtures) or xdebug (coverage mode)');

    it('records unchanged edges when no warm-up is registered', function (): void {
        $recorder = new Recorder;
        $recorder->activate();

        $recorder->beginTest('Some\Missing\TestClass', 'no warmup', '/project/tests/Feature/NoWarmupTest.php');
        tia_warmup_executed_source();
        $recorder->endTest();

        $files = array_map(basename(...), $recorder->perTestFiles()['/project/tests/Feature/NoWarmupTest.php'] ?? []);

        expect($files)->toContain('WarmupExecutedSource.php');
    })->skipOnWindows()->skip(fn (): bool => ! $driverCanInstrumentFixtures(), 'requires pcov (with pcov.directory covering the test fixtures) or xdebug (coverage mode)');

    it('does not invoke the warm-up callback without a coverage driver', function (): void {
        $recorder = new Recorder;
        $invoked = false;
        $recorder->warmupUsing(function () use (&$invoked): void {
            $invoked = true;
        });
        $recorder->activateLinkTracking();

        $recorder->beginTest('Some\Missing\TestClass', 'link tracking', '/project/tests/Feature/LinkTrackingTest.php');
        $recorder->endTest();

        expect($invoked)->toBeFalse();
    });
});
