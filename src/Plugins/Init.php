<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Console\Thanks;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Init implements HandlesArguments
{
    /**
     * The option the triggers the init job.
     */
    private const INIT_OPTION = '--init';

    /**
     * The files that will be created.
     */
    private const STUBS = [
        'phpunit.xml'     => 'phpunit.xml',
        'Pest.php'        => 'tests/Pest.php',
        'ExampleTest.php' => 'tests/ExampleTest.php',
    ];

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var TestSuite
     */
    private $testSuite;

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(TestSuite $testSuite, OutputInterface $output)
    {
        $this->testSuite = $testSuite;
        $this->output    = $output;
    }

    public function handleArguments(array $arguments): array
    {
        if (!array_key_exists(1, $arguments) || $arguments[1] !== self::INIT_OPTION) {
            return $arguments;
        }

        unset($arguments[1]);

        $this->init();

        return array_values($arguments);
    }

    private function init(): void
    {
        $testsBaseDir = "{$this->testSuite->rootPath}/tests";

        if (!is_dir($testsBaseDir)) {
            if (!mkdir($testsBaseDir) && !is_dir($testsBaseDir)) {
                $this->output->writeln(sprintf(
                    "\n  <fg=white;bg=red;options=bold> ERROR </> Directory `%s` was not created.</>",
                    $testsBaseDir
                ));

                return;
            }

            $this->output->writeln(
                '  <fg=black;bg=green;options=bold> DONE </> Created `tests` directory.</>',
            );
        }

        foreach (self::STUBS as $from => $to) {
            $fromPath = __DIR__ . "/../../stubs/init/{$from}";
            $toPath   = "{$this->testSuite->rootPath}/{$to}";

            if (file_exists($toPath)) {
                $this->output->writeln(sprintf(
                    '  <fg=black;bg=yellow;options=bold> INFO </> File `%s` already exists, skipped.</>',
                    $to
                ));

                continue;
            }

            if ($from === 'phpunit.xml' && file_exists($toPath . '.dist')) {
                $this->output->writeln(sprintf(
                    '  <fg=black;bg=yellow;options=bold> INFO </> File `%s` already exists, skipped.</>',
                    $to . '.dist'
                ));

                continue;
            }

            if (!copy($fromPath, $toPath)) {
                $this->output->writeln(sprintf(
                    '<fg=black;bg=red>[WARNING] Failed to copy stub `%s` to `%s`</>',
                    $from,
                    $toPath
                ));

                continue;
            }

            $this->output->writeln(sprintf(
                '  <fg=black;bg=green;options=bold> DONE </> Created `%s` file.</>',
                $to
            ));
        }

        $this->output->writeln(
            "\n  <fg=black;bg=green;options=bold> DONE </> Pest initialised.</>\n",
        );

        (new Thanks($this->output))();

        exit(0);
    }
}
