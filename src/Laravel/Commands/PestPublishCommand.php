<?php

declare(strict_types=1);

namespace Pest\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * @internal
 */
final class PestPublishCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pest:publish
                    {--existing : Publish and overwrite only the files that have already been published}
                    {--force : Overwrite any existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Pest test stubs';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (!File::isDirectory($stubsPath = $this->laravel->basePath('stubs'))) {
            File::makeDirectory($stubsPath, 0777, true, true);
        }

        // pest.stub and pest.unit.stub are compatible with `make:test --pest`
        $files = [
            realpath(__DIR__ . '/../../../stubs/Feature.php') => $stubsPath . '/pest.stub',
            realpath(__DIR__ . '/../../../stubs/Unit.php')    => $stubsPath . '/pest.unit.stub',
            realpath(__DIR__ . '/../../../stubs/Browser.php') => $stubsPath . '/pest.browser.stub',
        ];

        foreach ($files as $from => $to) {
            if ((!(bool) $this->option('existing') && (!File::exists($to) || (bool) $this->option('force')))
                || ((bool) $this->option('existing') && File::exists($to))) {
                File::put($to, File::get((string) $from));
            }
        }

        $this->output->success('Test stubs published successfully');
    }
}
