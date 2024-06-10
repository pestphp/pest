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
        $this->expectations[] = expect([
            'env',
        ])->not->toBeUsed();

        $this->expectations[] = expect([
            'exit',
        ])->not->toBeUsed();

        $this->expectations[] = expect('App\Http\Controllers')
            ->toHaveSuffix('Controller');

        $this->expectations[] = expect('App\Http\Middleware')
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App\Models')
            ->not->toHaveSuffix('Model');

        $this->expectations[] = expect('App\Http\Requests')
            ->toHaveSuffix('Request')
            ->toExtend('Illuminate\Foundation\Http\FormRequest')
            ->toHaveMethod('rules');

        $this->expectations[] = expect('App\Console\Commands')
            ->toHaveSuffix('Command')
            ->toExtend('Illuminate\Console\Command')
            ->toHaveMethod('handle')
            ->toImplementNothing();

        $this->expectations[] = expect('App\Exceptions')
            ->toImplement('Throwable');

        $this->expectations[] = expect('App\Mail')
            ->toHaveConstructor()
            ->toExtend('Illuminate\Mail\Mailable');

        $this->expectations[] = expect('App\Jobs')
            ->toHaveMethod('handle')
            ->toHaveConstructor();

        $this->expectations[] = expect('App\Notifications')
            ->toHaveConstructor()
            ->toExtend('Illuminate\Notifications\Notification');

        $this->expectations[] = expect('App\Providers')
            ->toHaveSuffix('ServiceProvider')
            ->toExtend('Illuminate\Support\ServiceProvider')
            ->not->toBeUsed();
    }
}
