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

        // A second test must start cleanly — endTest resets state even with no driver.
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
