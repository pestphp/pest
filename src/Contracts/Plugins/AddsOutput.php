<?php

declare(strict_types=1);

namespace Pest\Contracts\Plugins;

use Pest\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
interface AddsOutput
{
    /**
     * Allows to add custom output after the test suite was executed.
     */
    public function addOutput(TestSuite $testSuite, OutputInterface $output, int $testReturnCode): void;
}
