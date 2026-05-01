<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class TableTracker
{
    private const string CONTAINER_CLASS = '\\Illuminate\\Container\\Container';

    /**
     * App-scoped marker that makes `arm()` idempotent across the 774
     * per-test `setUp()` calls — Laravel reuses the same app instance
     * within a single test run, so without this guard we'd stack
     * one listener per test and each query would fire the closure
     * hundreds of times.
     */
    private const string MARKER = 'pest.tia.table-tracker-armed';

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

        if (! $app->bound('db')) {
            return;
        }

        $app->instance(self::MARKER, true);

        $listener = static function (object $query) use ($recorder): void {
            if (! property_exists($query, 'sql')) {
                return;
            }

            /** @var mixed $sql */
            $sql = $query->sql;

            if (! is_string($sql) || $sql === '') {
                return;
            }

            foreach (TableExtractor::fromSql($sql) as $table) {
                $recorder->linkTable($table);
            }
        };

        // Preferred path: `DatabaseManager::listen(Closure $callback)`.
        // It's a real method — `method_exists` returns false because
        // some Laravel versions compose it via a trait the reflection
        // probe can't always see, so we gate via `is_callable` instead.
        // This path pushes the listener onto every existing AND future
        // connection, which is what we want for a process-wide capture.
        /** @var object $db */
        $db = $app->make('db');

        if (is_callable([$db, 'listen'])) {
            /** @var callable $listen */
            $listen = [$db, 'listen'];
            $listen($listener);

            return;
        }

        // Fallback: register directly on the event dispatcher. Works
        // as long as every connection shares the same dispatcher
        // instance this app resolved to — true in vanilla setups,
        // but not guaranteed with connections instantiated pre-arm
        // that captured an older dispatcher.
        if (! $app->bound('events')) {
            return;
        }

        /** @var object $events */
        $events = $app->make('events');

        if (! method_exists($events, 'listen')) {
            return;
        }

        // Event class key intentionally has no leading backslash —
        // `Dispatcher::listen()` stores by the literal string and the
        // lookup at dispatch time uses `get_class($event)` (no
        // leading backslash), so a `\Illuminate\…` key would never
        // match the fired event.
        $events->listen('Illuminate\\Database\\Events\\QueryExecuted', $listener);
    }
}
