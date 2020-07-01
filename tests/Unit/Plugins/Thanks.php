<?php

use Pest\Plugins\Thanks;
use Symfony\Component\Console\Output\BufferedOutput;

it('outputs funding options when --thanks is used')->skip('The plugin uses `exit()` so not sure how to implement this');

it('does not output funding options when --thanks is not used', function () {
    $output = new BufferedOutput();
    $plugin = new Thanks($output);

    $plugin->handleArguments(['foo', 'bar']);
    assertEquals('', $output->fetch());
});
