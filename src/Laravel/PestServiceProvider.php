<?php

declare(strict_types=1);

namespace Pest\Laravel;

use Illuminate\Support\ServiceProvider;
use Pest\Laravel\Commands\PestDatasetCommand;
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
        }
    }
}
