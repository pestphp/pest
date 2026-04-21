<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Contracts;

/**
 * Storage contract for TIA's persistent state (graph, baselines, affected
 * set, worker partials, coverage snapshots). Modelled as a flat key/value
 * store of raw byte blobs so implementations can sit on top of whatever
 * backend fits — a directory, a shared cache, a remote object store — and
 * TIA's logic stays identical.
 *
 * @internal
 */
interface State
{
    /**
     * Returns the stored blob for `$key`, or `null` when the key is unset
     * or cannot be read.
     */
    public function read(string $key): ?string;

    /**
     * Atomically stores `$content` under `$key`. Existing value (if any) is
     * replaced. Implementations SHOULD guarantee that concurrent readers
     * never observe partial writes.
     */
    public function write(string $key, string $content): bool;

    /**
     * Removes `$key`. Returns true whether or not the key existed beforehand
     * — callers should treat a `true` result as "the key is now absent",
     * not "the key was present and has been removed."
     */
    public function delete(string $key): bool;

    public function exists(string $key): bool;

    /**
     * Returns every key whose name starts with `$prefix`. Used to collect
     * paratest worker partials (`worker-edges-<token>.json`, etc.) without
     * exposing backend-specific glob semantics.
     *
     * @return list<string>
     */
    public function keysWithPrefix(string $prefix): array;
}
