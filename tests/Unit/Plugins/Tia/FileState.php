<?php

declare(strict_types=1);

use Pest\Plugins\Tia\FileState;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pest-tia-file-state-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0755, true);
});

afterEach(function (): void {
    foreach (glob($this->root.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($this->root);
});

describe('keysWithPrefix()', function (): void {
    it('lists keys matching the prefix', function (): void {
        $state = new FileState($this->root);
        $state->write('worker-edges-a.json', '{}');
        $state->write('worker-edges-b.json', '{}');
        $state->write('worker-results-a.json', '{}');

        $keys = $state->keysWithPrefix('worker-edges-');
        sort($keys);

        expect($keys)->toBe(['worker-edges-a.json', 'worker-edges-b.json']);
    });

    it('ignores in-flight temporary files from concurrent writes', function (): void {
        $state = new FileState($this->root);
        $state->write('worker-edges-a.json', '{}');

        // Simulate another process mid-write: its temp file exists but has not been renamed yet.
        file_put_contents($this->root.'/worker-edges-b.json.'.bin2hex(random_bytes(4)).'.tmp', '{');

        expect($state->keysWithPrefix('worker-edges-'))->toBe(['worker-edges-a.json']);
    });
});
