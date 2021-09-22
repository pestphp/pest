<?php

use Pest\Plugins\Context;

test('environment is set to CI when --ci option is used', function () {
    $old_env = Context::getInstance()->env;

    $plugin = new Context();

    $plugin->handleArguments(['foo', '--ci', 'bar']);

    expect(Context::getInstance()->env)->toBe(Context::ENV_CI);

    Context::getInstance()->env = $old_env;
});

test('environment is set to Local when --ci option is not used', function () {
    $plugin = new Context();

    $plugin->handleArguments(['foo', 'bar', 'baz']);

    expect(Context::getInstance()->env)->toBe(Context::ENV_LOCAL);
});
