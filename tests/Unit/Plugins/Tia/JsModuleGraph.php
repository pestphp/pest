<?php

declare(strict_types=1);

use Pest\Plugins\Tia\JsModuleGraph;

function tiaJsModuleGraphCall(string $method, mixed ...$arguments): mixed
{
    return new ReflectionMethod(JsModuleGraph::class, $method)->invoke(null, ...$arguments);
}

describe('page directory discovery', function (): void {
    beforeEach(function (): void {
        $this->projectRoot = sys_get_temp_dir().'/pest-tia-js-module-graph-'.bin2hex(random_bytes(4));
        mkdir($this->projectRoot.'/resources/js/pages', 0755, true);
        file_put_contents($this->projectRoot.'/resources/js/pages/Dashboard.vue', "<template>ok</template>\n");
    });

    afterEach(function (): void {
        @unlink($this->projectRoot.'/resources/js/pages/Dashboard.vue');
        @rmdir($this->projectRoot.'/resources/js/pages');
        @rmdir($this->projectRoot.'/resources/js');
        @rmdir($this->projectRoot.'/resources');
        @rmdir($this->projectRoot);
    });

    it('resolves the pages directory with the casing it has on disk', function (): void {
        $expected = $this->projectRoot
            .DIRECTORY_SEPARATOR.'resources'
            .DIRECTORY_SEPARATOR.'js'
            .DIRECTORY_SEPARATOR.'pages';

        expect(tiaJsModuleGraphCall('firstExistingPagesDir', $this->projectRoot))->toBe($expected);
    });

    it('rejects a candidate whose own casing differs from disk', function (): void {
        expect(tiaJsModuleGraphCall('matchesDiskCasing', $this->projectRoot, 'resources/js/pages'))->toBeTrue()
            ->and(tiaJsModuleGraphCall('matchesDiskCasing', $this->projectRoot, 'resources/js/Pages'))->toBeFalse();
    });

    it('rejects a candidate whose parent segments differ from disk', function (): void {
        expect(tiaJsModuleGraphCall('matchesDiskCasing', $this->projectRoot, 'Resources/js/pages'))->toBeFalse();
    });

    it('rejects a candidate that is absent from disk', function (): void {
        expect(tiaJsModuleGraphCall('matchesDiskCasing', $this->projectRoot, 'assets/js/pages'))->toBeFalse();
    });
});
