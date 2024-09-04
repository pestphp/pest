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
        $this->expectations[] = expect('App\Traits')
            ->toBeTraits();

        $this->expectations[] = expect('App\Concerns')
            ->toBeTraits();

        $this->expectations[] = expect('App')
            ->not->toBeEnums()
            ->ignoring('App\Enums');

        $this->expectations[] = expect('App\Enums')
            ->toBeEnums();

        $this->expectations[] = expect('App\Features')
            ->toBeClasses();

        $this->expectations[] = expect('App\Features')
            ->toHaveMethod('resolve');

        $this->expectations[] = expect('App\Exceptions')
            ->classes()
            ->toImplement('Throwable');

        $this->expectations[] = expect('App')
            ->not->toImplement(Throwable::class)
            ->ignoring('App\Exceptions');

        $this->expectations[] = expect('App\Http\Middleware')
            ->classes()
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App\Models')
            ->classes()
            ->toExtend('Illuminate\Database\Eloquent\Model')
            ->ignoring('App\Models\Scopes');

        $this->expectations[] = expect('App\Models')
            ->classes()
            ->not->toHaveSuffix('Model');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Database\Eloquent\Model')
            ->ignoring('App\Models');

        $this->expectations[] = expect('App\Http\Requests')
            ->classes()
            ->toHaveSuffix('Request');

        $this->expectations[] = expect('App\Http\Requests')
            ->toExtend('Illuminate\Foundation\Http\FormRequest');

        $this->expectations[] = expect('App\Http\Requests')
            ->toHaveMethod('rules');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Foundation\Http\FormRequest')
            ->ignoring('App\Http\Requests');

        $this->expectations[] = expect('App\Console\Commands')
            ->classes()
            ->toHaveSuffix('Command');

        $this->expectations[] = expect('App\Console\Commands')
            ->classes()
            ->toExtend('Illuminate\Console\Command');

        $this->expectations[] = expect('App\Console\Commands')
            ->classes()
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Console\Command')
            ->ignoring('App\Console\Commands');

        $this->expectations[] = expect('App\Mail')
            ->classes()
            ->toExtend('Illuminate\Mail\Mailable');

        $this->expectations[] = expect('App\Mail')
            ->classes()
            ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Mail\Mailable')
            ->ignoring('App\Mail');

        $this->expectations[] = expect('App\Jobs')
            ->classes()
            ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

        $this->expectations[] = expect('App\Jobs')
            ->classes()
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App\Listeners')
            ->toHaveMethod('handle');

        $this->expectations[] = expect('App\Notifications')
            ->toExtend('Illuminate\Notifications\Notification');

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Notifications\Notification')
            ->ignoring('App\Notifications');

        $this->expectations[] = expect('App\Providers')
            ->toHaveSuffix('ServiceProvider');

        $this->expectations[] = expect('App\Providers')
            ->toExtend('Illuminate\Support\ServiceProvider');

        $this->expectations[] = expect('App\Providers')
            ->not->toBeUsed();

        $this->expectations[] = expect('App')
            ->not->toExtend('Illuminate\Support\ServiceProvider')
            ->ignoring('App\Providers');

        $this->expectations[] = expect('App')
            ->not->toHaveSuffix('ServiceProvider')
            ->ignoring('App\Providers');

        $this->expectations[] = expect('App')
            ->not->toHaveSuffix('Controller')
            ->ignoring('App\Http\Controllers');

        $this->expectations[] = expect('App\Http\Controllers')
            ->classes()
            ->toHaveSuffix('Controller');

        $this->expectations[] = expect('App\Http')
            ->toOnlyBeUsedIn('App\Http');

        $this->expectations[] = expect('App\Http\Controllers')
            ->not->toHavePublicMethodsBesides(['__construct', '__invoke', 'index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);

        $this->expectations[] = expect([
            'dd',
            'ddd',
            'dump',
            'env',
            'exit',
            'ray',
        ])->not->toBeUsed();
    }
}
