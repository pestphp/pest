<?php

use Pest\Console\Help;
use Symfony\Component\Console\Output\BufferedOutput;

it('outputs the help information when --help is used', function () {
    $output = new BufferedOutput;
    $plugin = new Help($output);

    $plugin();
    expect($output->fetch())->toContain('Pest Options:');
});
