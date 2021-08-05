<?php

namespace Pest\Console\Paratest;

use ParaTest\Runners\PHPUnit\ExecutableTest;

class ExecutablePestTest extends ExecutableTest
{
    /**
     * The number of tests in this file.
     *
     * @var int
     */
    private $testCount;

    public function __construct(string $path, int $testCount, bool $needsCoverage, bool $needsTeamcity, string $tmpDir)
    {
        parent::__construct($path, $needsCoverage, $needsTeamcity, $tmpDir);
        $this->testCount = $testCount;
    }

    public function getTestCount(): int
    {
        return $this->testCount;
    }

    protected function prepareOptions(array $options): array
    {
        return $options;
    }
}
