<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * Inertia-aware collaborator: during record mode, attributes every
 * Inertia component the test server-side renders to the currently-
 * running test file.
 *
 * Why this exists: a change to `resources/js/Pages/Users/Show.vue`
 * should only invalidate tests that actually rendered `Users/Show`.
 * The Laravel `WatchDefaults\Inertia` glob is a broad fallback — fine
 * for brand-new pages, but noisy once the graph has real data. With
 * this armed, each test's recorded edge set grows to include the
 * component names it returned through `Inertia::render()`, and
 * subsequent replay intersects page-file changes against that set.
 *
 * Mechanism: listen for `Illuminate\Foundation\Http\Events\RequestHandled`
 * on Laravel's event dispatcher. Inertia responses are identifiable by
 * either an `X-Inertia` header (XHR / JSON shape) or a `data-page`
 * attribute on the root `<div id="app">` (full HTML shape). Both carry
 * the component name in a structured payload we can parse cheaply.
 *
 * Same dep-free handshake as `BladeEdges` / `TableTracker`: string
 * class lookup + method-capability probes so Pest's `require` stays
 * Laravel-free.
 *
 * @internal
 */
final class InertiaEdges
{
    private const string CONTAINER_CLASS = '\\Illuminate\\Container\\Container';

    private const string REQUEST_HANDLED_EVENT = '\\Illuminate\\Foundation\\Http\\Events\\RequestHandled';

    /**
     * App-scoped marker that makes `arm()` idempotent across per-test
     * `setUp()` calls. Laravel reuses the same app across tests in
     * most configurations — without this guard we'd stack one
     * listener per test.
     */
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

    /**
     * Pulls the Inertia component name out of a Laravel response,
     * handling both XHR (`X-Inertia` + JSON body) and full HTML
     * (`<div id="app" data-page="…">`) shapes. Returns null for any
     * non-Inertia response so the caller can ignore it cheaply.
     */
    private static function extractComponent(object $response): ?string
    {
        // XHR path: Inertia sets an `X-Inertia: true` header and the
        // body is JSON with a `component` key.
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

        // Initial-load HTML path: Inertia embeds the page payload in a
        // `data-page` attribute on the root `<div id="app">`. We only
        // pay the regex cost when the body actually contains the
        // attribute, so non-Inertia HTML responses are effectively a
        // no-op.
        $content = self::readContent($response);

        if ($content === null || ! str_contains($content, 'data-page=')) {
            return null;
        }

        if (preg_match('/\sdata-page="([^"]+)"/', $content, $match) !== 1) {
            return null;
        }

        $decoded = json_decode(html_entity_decode($match[1]), true);

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
