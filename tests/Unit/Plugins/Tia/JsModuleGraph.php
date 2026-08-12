<?php

declare(strict_types=1);

use Pest\Plugins\Tia\JsModuleGraph;

function tiaJsModuleGraphCall(string $method, mixed ...$arguments): mixed
{
    return new ReflectionMethod(JsModuleGraph::class, $method)->invoke(null, ...$arguments);
}

function tiaJsModuleGraphProject(): string
{
    $root = sys_get_temp_dir().'/pest-tia-js-module-graph-'.bin2hex(random_bytes(6));

    mkdir($root.'/resources/js/pages', 0755, true);
    file_put_contents($root.'/vite.config.ts', "export default {}\n");
    file_put_contents($root.'/resources/js/pages/Dashboard.vue', "<template>ok</template>\n");

    return $root;
}

function tiaJsModuleGraphRemove(string $path): void
{
    if (! is_dir($path)) {
        @unlink($path);

        return;
    }

    @chmod($path, 0755);

    $entries = @scandir($path);

    foreach ($entries === false ? [] : $entries as $entry) {
        if ($entry === '.') {
            continue;
        }
        if ($entry === '..') {
            continue;
        }
        tiaJsModuleGraphRemove($path.'/'.$entry);
    }

    @rmdir($path);
}

beforeEach(function (): void {
    $this->projectRoot = tiaJsModuleGraphProject();
});

afterEach(function (): void {
    tiaJsModuleGraphRemove($this->projectRoot);
});

it('accepts a page directory candidate only when every segment matches the casing on disk', function (string $candidate, bool $expected): void {
    expect(tiaJsModuleGraphCall('matchesDiskCasing', $this->projectRoot, $candidate))->toBe($expected);
})->with([
    'exact' => ['resources/js/pages', true],
    'wrong leaf' => ['resources/js/Pages', false],
    'wrong parent' => ['Resources/js/pages', false],
    'absent' => ['assets/js/pages', false],
]);

it('resolves the pages directory with the casing it has on disk', function (): void {
    $expected = $this->projectRoot
        .DIRECTORY_SEPARATOR.'resources'
        .DIRECTORY_SEPARATOR.'js'
        .DIRECTORY_SEPARATOR.'pages';

    expect(tiaJsModuleGraphCall('firstExistingPagesDir', $this->projectRoot))->toBe($expected);
});

it('fingerprints a project whose js tree holds a directory it cannot open', function (): void {
    $locked = $this->projectRoot.'/resources/js/locked';

    mkdir($locked, 0755, true);
    file_put_contents($locked.'/Secret.vue', "<template>ok</template>\n");
    chmod($locked, 0000);

    if (is_readable($locked)) {
        $this->markTestSkipped('the current user reads directories regardless of their mode.');
    }

    expect(tiaJsModuleGraphCall('fingerprint', $this->projectRoot))->toBeString();
});
