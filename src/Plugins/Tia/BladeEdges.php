<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class BladeEdges
{
    private const string CONTAINER_CLASS = '\\Illuminate\\Container\\Container';

    /**
     * App-scoped marker that makes `arm()` idempotent. Tests call it
     * from every `setUp()`, and Laravel reuses the same app instance
     * across tests in most configurations — without this guard we'd
     * stack one composer per test and replay every one of them on
     * every view render.
     */
    private const string MARKER = 'pest.tia.blade-edges-armed';

    public static function arm(Recorder $recorder): void
    {
        if (! $recorder->isActive()) {
            return;
        }

        $containerClass = self::CONTAINER_CLASS;

        if (! class_exists($containerClass)) {
            return;
        }

        /** @var object $app */
        $app = $containerClass::getInstance();

        if (! method_exists($app, 'bound') || ! method_exists($app, 'make') || ! method_exists($app, 'instance')) {
            return;
        }

        if ($app->bound(self::MARKER)) {
            return;
        }

        if (! $app->bound('view')) {
            return;
        }

        $app->instance(self::MARKER, true);

        $factory = $app->make('view');

        if (! is_object($factory) || ! method_exists($factory, 'composer')) {
            return;
        }

        $factory->composer('*', static function (object $view) use ($recorder): void {
            if (! method_exists($view, 'getPath')) {
                return;
            }

            /** @var mixed $path */
            $path = $view->getPath();

            if (is_string($path) && $path !== '') {
                $recorder->linkSource($path);
            }
        });
    }
}
