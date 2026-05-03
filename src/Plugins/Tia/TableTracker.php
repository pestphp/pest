<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final class TableTracker
{
    private const string CONTAINER_CLASS = '\\Illuminate\\Container\\Container';

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

        /** @var object $db */
        $db = $app->make('db');

        if (is_callable([$db, 'listen'])) {
            /** @var callable $listen */
            $listen = [$db, 'listen'];
            $listen($listener);

            return;
        }

        if (! $app->bound('events')) {
            return;
        }

        /** @var object $events */
        $events = $app->make('events');

        if (! method_exists($events, 'listen')) {
            return;
        }

        $events->listen('Illuminate\\Database\\Events\\QueryExecuted', $listener);
    }
}
