<?php

use Pest\Plugins\Json as JsonPlugin;
use Pest\Support\JsonOutput;
use Symfony\Component\Console\Output\BufferedOutput;

it('has plugin')->assertTrue(class_exists(JsonPlugin::class));

it('has json output support')->assertTrue(class_exists(JsonOutput::class));

it('detects json mode from server variable', function () {
    $original = $_SERVER['PEST_JSON_OUTPUT'] ?? null;

    unset($_SERVER['PEST_JSON_OUTPUT']);
    expect(JsonOutput::isActive())->toBeFalse();

    $_SERVER['PEST_JSON_OUTPUT'] = 'true';
    expect(JsonOutput::isActive())->toBeTrue();

    $_SERVER['PEST_JSON_OUTPUT'] = 'false';
    expect(JsonOutput::isActive())->toBeFalse();

    if ($original !== null) {
        $_SERVER['PEST_JSON_OUTPUT'] = $original;
    } else {
        unset($_SERVER['PEST_JSON_OUTPUT']);
    }
});

it('adds --no-output argument when in json mode', function () {
    $original = $_SERVER['PEST_JSON_OUTPUT'] ?? null;
    $_SERVER['PEST_JSON_OUTPUT'] = 'true';

    $plugin = new JsonPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments(['--filter=test']);

    expect($arguments)->toContain('--no-output');

    if ($original !== null) {
        $_SERVER['PEST_JSON_OUTPUT'] = $original;
    } else {
        unset($_SERVER['PEST_JSON_OUTPUT']);
    }
});

it('does not modify arguments when not in json mode', function () {
    $original = $_SERVER['PEST_JSON_OUTPUT'] ?? null;
    unset($_SERVER['PEST_JSON_OUTPUT']);

    $plugin = new JsonPlugin(new BufferedOutput);
    $arguments = $plugin->handleArguments(['--filter=test']);

    expect($arguments)->toBe(['--filter=test']);

    if ($original !== null) {
        $_SERVER['PEST_JSON_OUTPUT'] = $original;
    }
});
