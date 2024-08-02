<?php

use Pest\Plugins\Environment;

test('environment is set to CI when --ci option is used', function () {
    $previousName = Environment::name();

    $plugin = new Environment;

    $plugin->handleArguments(['foo', '--ci', 'bar']);

    expect(Environment::name())->toBe(Environment::CI);

    Environment::name($previousName);
});

test('environment is set to Local when --ci option is not used', function () {
    $plugin = new Environment;

    $plugin->handleArguments(['foo', 'bar', 'baz']);

    expect(Environment::name())->toBe(Environment::LOCAL);
});
