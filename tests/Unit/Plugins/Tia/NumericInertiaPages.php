<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Recorder;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;

beforeEach(function (): void {
    Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
});

afterEach(function (): void {
    Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
});

it('selects the tests of a changed Inertia page whose name is a number', function (): void {
    $root = (string) realpath(sys_get_temp_dir());
    $graph = new Graph($root);
    $graph->replaceTestInertiaComponents([
        $root.'/tests/Feature/NumericPageTest.php' => ['123'],
        $root.'/tests/Feature/DashboardTest.php' => ['Dashboard'],
    ]);

    expect($graph->affected(['resources/js/Pages/123.vue']))->toBe(['tests/Feature/NumericPageTest.php']);
});

it('keeps an Inertia page whose name is a number after the graph is decoded', function (): void {
    $root = (string) realpath(sys_get_temp_dir());
    $graph = new Graph($root);
    $graph->replaceTestInertiaComponents([
        $root.'/tests/Feature/NumericPageTest.php' => ['123'],
    ]);

    $decoded = Graph::decode((string) $graph->encode(), $root);

    expect($decoded?->affected(['resources/js/Pages/123.vue']))->toBe(['tests/Feature/NumericPageTest.php']);
});

it('records an Inertia page whose name is a number as a string', function (): void {
    $recorder = new Recorder;
    $recorder->activateLinkTracking();

    $recorder->beginTest('Some\Missing\TestClass', 'it renders', '/project/tests/Feature/NumericPageTest.php');
    $recorder->linkInertiaComponent('123');
    $recorder->endTest();

    expect($recorder->perTestInertiaComponents())->toBe(['/project/tests/Feature/NumericPageTest.php' => ['123']]);
});

it('selects the tests of a shared module of an Inertia page whose name is a number', function (): void {
    $root = (string) realpath(sys_get_temp_dir());
    $graph = new Graph($root);
    $graph->replaceTestInertiaComponents([
        $root.'/tests/Feature/NumericPageTest.php' => ['123'],
    ]);
    $graph->replaceJsFileToComponents(['resources/js/Components/Chart.vue' => ['123']]);

    expect($graph->affected(['resources/js/Components/Chart.vue']))->toBe(['tests/Feature/NumericPageTest.php']);
});
