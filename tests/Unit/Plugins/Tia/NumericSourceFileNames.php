<?php

declare(strict_types=1);

use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\WatchPatterns;
use Pest\Support\Container;

beforeEach(function (): void {
    Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
});

afterEach(function (): void {
    Container::getInstance()->add(WatchPatterns::class, new WatchPatterns);
});

it('selects the tests of a changed source file whose name is a number', function (): void {
    $graph = new Graph(sys_get_temp_dir());
    $graph->link('tests/Feature/NumericTest.php', '123');
    $graph->link('tests/Feature/OtherTest.php', 'app/Other.php');

    expect($graph->affected(['123']))->toBe(['tests/Feature/NumericTest.php']);
});

it('selects the tests of a numeric source file after the graph is decoded', function (): void {
    $graph = new Graph(sys_get_temp_dir());
    $graph->link('tests/Feature/NumericTest.php', '123');

    $decoded = Graph::decode($graph->encode(), sys_get_temp_dir());

    expect($decoded?->affected(['123']))->toBe(['tests/Feature/NumericTest.php']);
});
