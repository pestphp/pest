<?php

declare(strict_types=1);

namespace Pest\Console;

use Pest\Actions\AddsCoverage;
use Pest\Actions\AddsDefaults;
use Pest\Actions\AddsTests;
use Pest\Actions\LoadStructure;
use Pest\Actions\ValidatesConfiguration;
use Pest\TestSuite;
use PHPUnit\TextUI\Command as BaseCommand;
use PHPUnit\TextUI\TestRunner;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Command extends BaseCommand
{
    /**
     * Holds the current testing suite.
     *
     * @var TestSuite
     */
    private $testSuite;

    /**
     * Holds the current console output.
     *
     * @var OutputInterface
     */
    private $output;

    /**
     * Creates a new instance of the command class.
     */
    public function __construct(TestSuite $testSuite, OutputInterface $output)
    {
        $this->testSuite = $testSuite;
        $this->output    = $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleArguments(array $argv): void
    {
        /*
         * First, let's handle pest is own `--coverage` param.
         */
        $argv = AddsCoverage::from($this->testSuite, $argv);

        /*
         * Next, as usual, let's send the console arguments to PHPUnit.
         */
        parent::handleArguments($argv);

        /*
         * Finally, let's validate the configuration. Making
         * sure all options are yet supported by Pest.
         */
        ValidatesConfiguration::in($this->arguments);
    }

    /**
     * Creates a new PHPUnit test runner.
     */
    protected function createRunner(): TestRunner
    {
        /*
         * First, let's add the defaults we use on `pest`. Those
         * are the printer class, and others that may be appear.
         */
        $this->arguments = AddsDefaults::to($this->arguments);

        $testRunner = new TestRunner($this->arguments['loader']);
        $testSuite  = $this->arguments['test'];

        LoadStructure::in($this->testSuite->rootPath);

        AddsTests::to($testSuite, $this->testSuite);

        return $testRunner;
    }

    /**
     * {@inheritdoc}
     */
    public function run(array $argv, bool $exit = true): int
    {
        $result = parent::run($argv, false);

        if ($result === 0 && $this->testSuite->coverage) {
            Coverage::show($this->output);
        }

        exit($result);
    }
}
