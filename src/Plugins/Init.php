<?php

declare(strict_types=1);

namespace Pest\Plugins;

use Composer\InstalledVersions;
use Pest\Console\Thanks;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Support\View;
use Pest\TestSuite;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
        'TestCase.php' => 'tests/TestCase.php',
        'Unit/ExampleTest.php' => 'tests/Unit/ExampleTest.php',
        'Feature/ExampleTest.php' => 'tests/Feature/ExampleTest.php',
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
        if ($this->isLaravelInstalled()) {
            exit($this->initLaravel());
        }

        $testsBaseDir = "{$this->testSuite->rootPath}/tests";

        if (! is_dir($testsBaseDir)) {
            mkdir($testsBaseDir);
        }

        View::render('components.badge', [
            'type' => 'INFO',
            'content' => 'Preparing tests directory.',
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

    private function initLaravel(): int
    {
        $command = [
            'composer', 'require',
            'pestphp/pest-plugin-laravel 2.x-dev',
            '--dev',
        ];

        $result = (new Process($command, $this->testSuite->rootPath, ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output): void {
                $this->output->write($output);
            });

        if ($result > 0) {
            return $result;
        }

        $command = [
            'php', 'artisan',
            'pest:install',
            '--no-interaction',
        ];

        return (new Process($command, $this->testSuite->rootPath, ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output): void {
                $this->output->write($output);
            });
    }

    /**
     * Checks if laravel is installed through Composer
     */
    private function isLaravelInstalled(): bool
    {
        return InstalledVersions::isInstalled('laravel/laravel');
    }
}
