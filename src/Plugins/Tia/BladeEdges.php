<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Laravel-only collaborator: during record mode, attributes every
 * rendered Blade view to the currently-running test.
 *
 * Why this exists: the coverage driver only sees compiled view files
 * under `storage/framework/views/<hash>.php`, not the `.blade.php`
 * source. Without a dedicated hook TIA has no edges for blade files,
 * so it leans on the Laravel WatchDefault's broad "any .blade.php
 * change → every feature test" fallback. Safe but noisy — editing a
 * single partial re-runs the whole suite.
 *
 * With this armed at record time, each test's edge set grows to
 * include the precise `.blade.php` files it rendered (directly or
 * through `@include`, layouts, components, Livewire, Inertia root
 * views — anything that goes through Laravel's view factory fires
 * `View::composer('*')`). Replay then invalidates exactly the tests
 * that rendered the changed template.
 *
 * Implementation note: everything Laravel-touching goes through
 * string class names, `class_exists`, and `method_exists` so Pest
 * core doesn't pull `illuminate/container` into its `require`.
 *
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
