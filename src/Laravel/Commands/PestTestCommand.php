<?php

declare(strict_types=1);

namespace Pest\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pest\Exceptions\InvalidConsoleArgument;
use Pest\Support\Str;

/**
 * @internal
 */
final class PestTestCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pest:test {name : The name of the file} {--unit : Create a unit test} {--dusk : Create a Dusk test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new test file';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        /** @var string $name */
        $name = $this->argument('name');

        $type = ((bool) $this->option('unit')) ? 'Unit' : (((bool) $this->option('dusk')) ? 'Browser' : 'Feature');

        $relativePath = sprintf('tests/%s/%s.php',
            $type,
            ucfirst($name)
        );

        /* @phpstan-ignore-next-line */
        $target = base_path($relativePath);

        if (!File::isDirectory(dirname($target))) {
            File::makeDirectory(dirname($target), 0777, true, true);
        }

        if (File::exists($target)) {
            throw new InvalidConsoleArgument(sprintf('%s already exist', $target));
        }

        $contents = File::get(implode(DIRECTORY_SEPARATOR, [
            dirname(__DIR__, 3),
            'stubs',
            sprintf('%s.php', $type),
        ]));

        $name = mb_strtolower($name);
        $name = Str::endsWith($name, 'test') ? mb_substr($name, 0, -4) : $name;

        File::put($target, str_replace('{name}', $name, $contents));
        $message = sprintf('`%s` created successfully.', $relativePath);

        $this->output->success($message);
    }
}
