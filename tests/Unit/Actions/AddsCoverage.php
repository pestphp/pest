<?php

use Pest\Actions\AddsCoverage;
use Pest\TestSuite;

it('adds coverage if --coverage exist', function () {
    $arguments = ['--coverage'];
    $testSuite = new TestSuite(getcwd());
    assertFalse($testSuite->coverage);

    $arguments = AddsCoverage::from($testSuite, $arguments);
    assertEquals(['--coverage-php', \Pest\Console\Coverage::getPath()], $arguments);
});
