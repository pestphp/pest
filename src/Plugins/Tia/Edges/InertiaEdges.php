<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Edges;

use Pest\Plugins\Tia\Recorder;

/**
 * @internal
 */
final class InertiaEdges
{
    private const string CONTAINER_CLASS = '\\Illuminate\\Container\\Container';

    private const string REQUEST_HANDLED_EVENT = 'Illuminate\\Foundation\\Http\\Events\\RequestHandled';

    private const string MARKER = 'pest.tia.inertia-edges-armed';

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

        if (! $app->bound('events')) {
            return;
        }

        $app->instance(self::MARKER, true);

        /** @var object $events */
        $events = $app->make('events');

        if (! method_exists($events, 'listen')) {
            return;
        }

        $events->listen(self::REQUEST_HANDLED_EVENT, static function (object $event) use ($recorder): void {
            if (! property_exists($event, 'response')) {
                return;
            }

            /** @var mixed $response */
            $response = $event->response;

            if (! is_object($response)) {
                return;
            }

            $component = self::extractComponent($response);

            if ($component !== null) {
                $recorder->linkInertiaComponent($component);
            }
        });
    }

    private static function extractComponent(object $response): ?string
    {
        if (property_exists($response, 'headers') && is_object($response->headers)) {
            $headers = $response->headers;

            if (method_exists($headers, 'has') && $headers->has('X-Inertia')) {
                $content = self::readContent($response);

                if ($content !== null) {
                    /** @var mixed $decoded */
                    $decoded = json_decode($content, true);

                    if (is_array($decoded)
                        && isset($decoded['component'])
                        && is_string($decoded['component'])
                        && $decoded['component'] !== '') {
                        return $decoded['component'];
                    }
                }
            }
        }

        $content = self::readContent($response);

        if ($content === null) {
            return null;
        }

        if (str_contains($content, 'type="application/json"')
            && preg_match('#<script\b(?=[^>]*\bdata-page="app")(?=[^>]*\btype="application/json")[^>]*>(.+?)</script>#s', $content, $match) === 1) {
            $component = self::componentFromJson(html_entity_decode($match[1]));

            if ($component !== null) {
                return $component;
            }
        }

        if (str_contains($content, 'data-page=')
            && preg_match('/\sdata-page="(\{[^"]+\})"/', $content, $match) === 1) {
            $component = self::componentFromJson(html_entity_decode($match[1]));

            if ($component !== null) {
                return $component;
            }
        }

        return null;
    }

    private static function componentFromJson(string $json): ?string
    {
        /** @var mixed $decoded */
        $decoded = json_decode($json, true);

        if (is_array($decoded)
            && isset($decoded['component'])
            && is_string($decoded['component'])
            && $decoded['component'] !== '') {
            return $decoded['component'];
        }

        return null;
    }

    private static function readContent(object $response): ?string
    {
        if (! method_exists($response, 'getContent')) {
            return null;
        }

        /** @var mixed $content */
        $content = $response->getContent();

        return is_string($content) ? $content : null;
    }
}
