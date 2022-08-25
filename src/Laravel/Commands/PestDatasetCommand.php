<?php

declare(strict_types=1);

namespace Pest\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pest\Exceptions\InvalidConsoleArgument;

use function Pest\testDirectory;

use Pest\TestSuite;

/**
 * @internal
 */
final class PestDatasetCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pest:dataset {name : The name of the dataset}
                                         {--test-directory=tests : The name of the tests directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new dataset file';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /* @phpstan-ignore-next-line */
        TestSuite::getInstance(base_path(), $this->option('test-directory'));

        /** @var string $name */
        $name = $this->argument('name');

        $relativePath   = sprintf(testDirectory('Datasets/%s.php'), ucfirst($name));

        /* @phpstan-ignore-next-line */
        $target         = base_path($relativePath);

        if (File::exists($target)) {
            throw new InvalidConsoleArgument(sprintf('%s already exist', $target));
        }

        if (!File::exists(dirname($relativePath))) {
            File::makeDirectory(dirname($relativePath));
        }

        $contents = File::get(implode(DIRECTORY_SEPARATOR, [
            dirname(__DIR__, 3),
            'stubs',
            'Dataset.php',
        ]));

        $name     = mb_strtolower($name);
        $contents = str_replace('{dataset_name}', $name, $contents);

        $element  = Str::singular($name);
        $contents = str_replace('{dataset_element}', $element, $contents);
        File::put($target, str_replace('{dataset_name}', $name, $contents));
        $message = sprintf('`%s` created successfully.', $relativePath);

        $this->output->success($message);
    }
}
