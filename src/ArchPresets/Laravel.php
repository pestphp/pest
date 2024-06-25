<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Throwable;

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
            'dd',
            'ddd',
            'env',
            'exit',
        ])->not->toBeUsed();

        $this->expectations[] = expect('App')
            ->not->toBeEnums()
            ->ignoring('App\Enums');

        $this->expectations[] = expect('App\Enums')
            ->toBeEnums();

        $this->expectations[] = expect('App\Exceptions')
            ->classes()
            ->toImplement('Throwable');

        $this->expectations[] = expect('App')
            ->not->toImplement(Throwable::class)
            ->ignoring('App\Exceptions');

        $this->expectations[] = expect('App\Http\Controllers')
            ->classes()
            ->toHaveSuffix('Controller');

        $this->expectations[] = expect('App')
            ->not->toHaveSuffix('Controller')
            ->ignoring('App\Http\Controllers');

        $this->expectations[] = expect('App\Http\Middleware')
            ->classes()
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App\Models')
            ->classes()
            ->toExtend('Illuminate\Database\Eloquent\Model')
            ->not->toHaveSuffix('Model');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Database\Eloquent\Model')
            ->ignoring('App\Models');

        $this->expectations[] = expect('App\Http\Requests')
            ->classes()
            ->toHaveSuffix('Request')
            ->toExtend('Illuminate\Foundation\Http\FormRequest') // @phpstan-ignore-line
            ->toHaveMethod('rules');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Foundation\Http\FormRequest')
            ->ignoring('App\Http\Requests');

        $this->expectations[] = expect('App\Console\Commands')
            ->classes()
            ->toHaveSuffix('Command')
            ->toExtend('Illuminate\Console\Command') // @phpstan-ignore-line
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Console\Command')
            ->ignoring('App\Console\Commands');

        $this->expectations[] = expect('App\Mail')
            ->classes()
            ->toExtend('Illuminate\Mail\Mailable'); // @phpstan-ignore-line

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Mail\Mailable')
            ->ignoring('App\Mail');

        $this->expectations[] = expect('App\Jobs')
            ->classes()
            ->toImplement('Illuminate\Contracts\Queue\ShouldQueue')
            ->toUseTraits([
                'Illuminate\Bus\Queueable',
                'Illuminate\Foundation\Bus\Dispatchable',
                'Illuminate\Queue\InteractsWithQueue',
                'Illuminate\Queue\SerializesModels',
            ])->toHaveMethod('handle');

        $this->expectations[] = expect('App\Listeners')
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App\Notifications')
            ->toExtend('Illuminate\Notifications\Notification'); // @phpstan-ignore-line

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Notifications\Notification')
            ->ignoring('App\Notifications');

        $this->expectations[] = expect('App\Providers') // @phpstan-ignore-line
            ->toHaveSuffix('ServiceProvider')
            ->toExtend('Illuminate\Support\ServiceProvider') // @phpstan-ignore-line
            ->not->toBeUsed();

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Support\ServiceProvider')
            ->not->toHaveSuffix('ServiceProvider')
            ->ignoring('App\Providers');
    }
}
