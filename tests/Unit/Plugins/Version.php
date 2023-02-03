<?php

use Pest\Plugins\Version;
use Symfony\Component\Console\Output\BufferedOutput;

use function Pest\version;

it('outputs the version when --version is used', function () {
    $output = new BufferedOutput();
    $plugin = new Version($output);

    $plugin->handleArguments(['foo', '--version']);
    expect($output->fetch())->toContain('Pest    ' . version());
});

it('do not outputs version when --version is not used', function () {
    $output = new BufferedOutput();
    $plugin = new Version($output);

    $plugin->handleArguments(['foo', 'bar']);
    expect($output->fetch())->toBe('');
});
