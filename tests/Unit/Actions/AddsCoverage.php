<?php

use Pest\Actions\AddsCoverage;
use Pest\TestSuite;

it('adds coverage if --coverage exist', function () {
    $testSuite = new TestSuite(getcwd());
    assertFalse($testSuite->coverage);

    $arguments = AddsCoverage::from($testSuite, []);
    assertEquals([], $arguments);
    assertFalse($testSuite->coverage);

    $arguments = AddsCoverage::from($testSuite, ['--coverage']);
    assertEquals(['--coverage-php', \Pest\Console\Coverage::getPath()], $arguments);
    assertTrue($testSuite->coverage);
});

it('adds coverage if --min exist', function () {
    $testSuite = new TestSuite(getcwd());
    assertEquals($testSuite->coverageMin, 0.0);

    assertFalse($testSuite->coverage);
    AddsCoverage::from($testSuite, []);
    assertEquals($testSuite->coverageMin, 0.0);

    AddsCoverage::from($testSuite, ['--min=2']);
    assertEquals($testSuite->coverageMin, 2.0);

    AddsCoverage::from($testSuite, ['--min=2.4']);
    assertEquals($testSuite->coverageMin, 2.4);
});
