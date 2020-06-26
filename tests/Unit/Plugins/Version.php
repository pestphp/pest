<?php

use Pest\Plugins\Version;
use Symfony\Component\Console\Output\BufferedOutput;

it('outputs the version when --version is used', function () {
    $output = new BufferedOutput();
    $plugin = new Version($output);

    $plugin->handleArguments(['foo', '--version']);
    assertStringContainsString('Pest    0.2.2', $output->fetch());
});

it('do not outputs version when --version is not used', function () {
    $output = new BufferedOutput();
    $plugin = new Version($output);

    $plugin->handleArguments(['foo', 'bar']);
    assertEquals('', $output->fetch());
});
