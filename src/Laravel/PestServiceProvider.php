<?php

declare(strict_types=1);

namespace Pest\Laravel;

use Illuminate\Support\ServiceProvider;
use Laravel\Dusk\Console\DuskCommand;
use Pest\Laravel\Commands\PestDatasetCommand;
use Pest\Laravel\Commands\PestDuskCommand;
use Pest\Laravel\Commands\PestInstallCommand;
use Pest\Laravel\Commands\PestTestCommand;

final class PestServiceProvider extends ServiceProvider
{
    /**
     * Register artisan commands.
     */
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PestInstallCommand::class,
                PestTestCommand::class,
                PestDatasetCommand::class,
            ]);

            if (class_exists(DuskCommand::class)) {
                $this->commands([
                    PestDuskCommand::class,
                ]);
            }
        }
    }
}
