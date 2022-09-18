<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Pest\Console\Thanks;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\View;
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
        'phpunit.xml' => 'phpunit.xml',
        'Pest.php' => 'tests/Pest.php',
        'ExampleTest.php' => 'tests/ExampleTest.php',
    ];

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(
        private readonly TestSuite $testSuite,
        private readonly OutputInterface $output
    ) {
        // ..
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        if (! array_key_exists(1, $arguments)) {
            return $arguments;
        }
        if ($arguments[1] !== self::INIT_OPTION) {
            return $arguments;
        }
        unset($arguments[1]);

        $this->init();

        return array_values($arguments);
    }

    private function init(): void
    {
        $testsBaseDir = "{$this->testSuite->rootPath}/tests";

        if (! is_dir($testsBaseDir)) {
            mkdir($testsBaseDir);
        }

        $this->output->writeln([
            '',
            '  <fg=white;bg=blue;options=bold> INFO </> Preparing tests directory.</>',
            '',
        ]);

        foreach (self::STUBS as $from => $to) {
            $fromPath = __DIR__."/../../stubs/init/{$from}";
            $toPath = "{$this->testSuite->rootPath}/{$to}";

            if (file_exists($toPath)) {
                View::render('components.two-column-detail', [
                    'left' => $to,
                    'right' => 'File already exists.',
                ]);

                continue;
            }

            copy($fromPath, $toPath);

            View::render('components.two-column-detail', [
                'left' => $to,
                'right' => 'File created.',
            ]);
        }

        View::render('components.new-line');

        (new Thanks($this->output))();

        exit(0);
    }
}
