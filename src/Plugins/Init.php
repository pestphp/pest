<?php

declare(strict_types=1);

namespace Pest\Plugins;

use App\Console\Kernel;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Process;
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
        View::render('components.badge', [
            'type' => 'INFO',
            'content' => 'Laravel installation detected, pest-plugin-laravel will be installed.',
        ]);

        exec('composer require pestphp/pest-plugin-laravel 2.x-dev', result_code: $result);

        /** @var int $result */
        if ($result > 0) {
            View::render('components.badge', [
                'type' => 'ERROR',
                'content' => 'Something went wrong while installing pest-plugin-laravel package. Please refer the above output for more info.',
            ]);

            return $result;
        }

        View::render('components.badge', [
            'type' => 'INFO',
            'content' => 'Running artisan command to install Pest.',
        ]);

        $app = require $this->testSuite->rootPath.'/bootstrap/app.php';
        /** @phpstan-ignore-next-line */
        $app->make(Kernel::class)->bootstrap();

        /** @phpstan-ignore-next-line */
        $result = Process::run('php artisan pest:install --no-interaction');

        if ($result->failed()) {
            $this->output->writeln($result->errorOutput());

            View::render('components.badge', [
                'type' => 'ERROR',
                'content' => 'Something went wrong while installing Pest in laravel. Please refer the above output for more info.',
            ]);

            return $result->exitCode();
        }

        $this->output->writeln($result->output());

        View::render('components.two-column-detail', [
            'left' => 'pest-plugin-laravel',
            'right' => 'Installed',
        ]);

        View::render('components.two-column-detail', [
            'left' => 'Pest',
            'right' => 'Installed in Laravel',
        ]);

        View::render('components.new-line');

        return 0;
    }

    private function isLaravelInstalled(): bool
    {
        return InstalledVersions::isInstalled('laravel/laravel');
    }
}
