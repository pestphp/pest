<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Emitters\DispatchingEmitter;
use PHPUnit\Event;
use ReflectionClass;

/**
 * @internal
 */
final class BootEmitter
{
    /**
     * Boots the Event Emitter.
     */
    public function __invoke(): void
    {
        if (!($baseEmitter = Event\Facade::emitter()) instanceof DispatchingEmitter) {
            $reflectedClass = new ReflectionClass(Event\Facade::class);

            $reflectedClass->setStaticPropertyValue('emitter', new DispatchingEmitter(
                $baseEmitter,
            ));
        }
    }
}
