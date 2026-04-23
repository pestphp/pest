<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Laravel-only collaborator: during record mode, attributes every SQL
 * table the test body queries to the currently-running test.
 *
 * Why this exists: the coverage graph can tell us which PHP files a
 * test touched but cannot distinguish "this test depends on the
 * `users` table" from "this test depends on `questions`". That
 * distinction is the whole point of surgical migration invalidation —
 * a column rename in `create_questions_table.php` should only re-run
 * tests whose body actually queried `questions`.
 *
 * Mechanism: install a listener on Laravel's event dispatcher that
 * subscribes to `Illuminate\Database\Events\QueryExecuted`. Each
 * query string is piped through `TableExtractor::fromSql()`; DDL is
 * filtered at extraction time so migrations running in `setUp` don't
 * attribute every table to every test.
 *
 * Same dep-free handshake as `BladeEdges`: string class lookup +
 * method-capability probes so Pest's `require` stays Laravel-free.
 *
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

        $events->listen('\\Illuminate\\Database\\Events\\QueryExecuted', $listener);
    }
}
