<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

/**
 * @internal
 */
final class Laravel extends AbstractPreset
{
    /**
     * Executes the arch preset.
     */
    public function execute(): void
    {
        $expectations = [
            expect(['env', 'exit'])->not->toBeUsed(),
            expect('App\Http\Controllers')->toHaveSuffix('Controller'),
            expect('App\Http\Middleware')->toHaveMethod('handle'),
            expect('App\Models')->not->toHaveSuffix('Model'),
            expect('App\Exceptions')->toImplement('Throwable'),
            expect('App\Mail')->toExtend('Illuminate\Mail\Mailable'),
            expect('App\Jobs')->toHaveMethod('handle'),
            expect('App\Listeners')->toHaveMethod('handle'),
            expect('App\Notifications')->toExtend('Illuminate\Notifications\Notification'),
            expect('App\Http\Requests')->toHaveSuffix('Request')->toExtend('Illuminate\Foundation\Http\FormRequest')->toHaveMethod('rules'),
            expect('App\Console\Commands')->toHaveSuffix('Command')->toExtend('Illuminate\Console\Command')->toHaveMethod('handle'),
            expect('App\Providers')->toHaveSuffix('ServiceProvider')->toExtend('Illuminate\Support\ServiceProvider')->not->toBeUsed(),
        ];

        $this->updateExpectations($expectations);
    }
}
