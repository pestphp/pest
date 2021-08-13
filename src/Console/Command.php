<?php

declare(strict_types=1);

namespace Pest\Console;

use Pest\Actions\AddsDefaults;
use Pest\Actions\AddsTests;
use Pest\Actions\InteractsWithPlugins;
use Pest\Actions\LoadStructure;
use Pest\Actions\ValidatesConfiguration;
use Pest\Plugins\Version;
use Pest\Support\Container;
use Pest\TestSuite;
use PHPUnit\Framework\TestSuite as BaseTestSuite;
use PHPUnit\TextUI\Command as BaseCommand;
use PHPUnit\TextUI\TestRunner;
use SebastianBergmann\FileIterator\Facade as FileIteratorFacade;
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
     *
     * @phpstan-ignore-next-line
     *
     * @param array<int, string> $argv
     */
    protected function handleArguments(array $argv): void
    {
        $argv = InteractsWithPlugins::handleArguments($argv);

        parent::handleArguments($argv);

        /*
         * Let's validate the configuration. Making
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

        if (is_string($testSuite)) {
            if (\is_dir($testSuite)) {
                /** @var string[] $files */
                $files = (new FileIteratorFacade())->getFilesAsArray(
                    $testSuite,
                    $this->arguments['testSuffixes']
                );
            } else {
                $files = [$testSuite];
            }

            $testSuite = new BaseTestSuite($testSuite);

            $testSuite->addTestFiles($files);

            $this->arguments['test'] = $testSuite;
        }

        AddsTests::to($testSuite, $this->testSuite);

        return $testRunner;
    }

    /**
     * {@inheritdoc}
     *
     * @phpstan-ignore-next-line
     *
     * @param array<int, string> $argv
     */
    public function run(array $argv, bool $exit = true): int
    {
        LoadStructure::in($this->testSuite->rootPath);

        $result = parent::run($argv, false);
        $result = InteractsWithPlugins::addOutput($result);

        exit($result);
    }

    protected function showHelp(): void
    {
        /** @var Version $version */
        $version = Container::getInstance()->get(Version::class);
        $version->handleArguments(['--version']);
        parent::showHelp();

        (new Help($this->output))();
    }
}
