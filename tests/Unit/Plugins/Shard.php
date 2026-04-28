<?php

use Pest\Plugins\Shard as ShardPlugin;
use Symfony\Component\Console\Output\OutputInterface;

test('shard includes module tests returned by list-tests', function () {
    $plugin = new ShardPlugin($this->createMock(OutputInterface::class));

    $allTests = \Closure::bind(fn (array $arguments): array => $this->allTests($arguments), $plugin, ShardPlugin::class);

    expect($allTests(['bin/pest', 'modules/Example/tests/ShardModuleExample.php']))
        ->toContain('Modules\\Example\\tests\\ShardModuleExample');
});
