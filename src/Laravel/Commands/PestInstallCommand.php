<?php

declare(strict_types=1);

namespace Pest\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pest\Console\Thanks;
use Pest\Exceptions\InvalidConsoleArgument;
use Pest\TestSuite;

use function Pest\testDirectory;

/**
 * @internal
 */
final class PestInstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pest:install {--test-directory=tests : The name of the tests directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates Pest resources in your current PHPUnit test suite';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /* @phpstan-ignore-next-line */
        TestSuite::getInstance(base_path(), $this->option('test-directory'));

        /* @phpstan-ignore-next-line */
        $pest    = base_path(testDirectory('Pest.php'));
        $stubs   = 'stubs/Laravel';

        if (File::exists($pest)) {
            throw new InvalidConsoleArgument(sprintf('%s already exist', $pest));
        }

        File::copy(implode(DIRECTORY_SEPARATOR, [
            dirname(__DIR__, 3),
            $stubs,
            'Pest.php',
        ]), $pest);

        $this->output->success('`tests/Pest.php` created successfully.');

        if (!(bool) $this->option('no-interaction')) {
            (new Thanks($this->output))();
        }
    }
}
