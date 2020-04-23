<?php

use Pest\Console\Coverage;

it('generates coverage based on file input', function () {
    assertEquals([
        '4..6', '102',
    ], Coverage::getMissingCoverage(new class() {
        public function getCoverageData(): array
        {
            return [
                1   => ['foo'],
                2   => ['bar'],
                4   => [],
                5   => [],
                6   => [],
                7   => null,
                100 => null,
                101 => ['foo'],
                102 => [],
            ];
        }
    }));
});
