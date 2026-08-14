<?php

declare(strict_types=1);

use Pest\Plugins\Tia\SourceScope;

describe('noisePaths()', function (): void {
    it('lists the directories that hold no project source', function (): void {
        expect(SourceScope::noisePaths('/project'))
            ->toContain('/project/vendor/')
            ->toContain('/project/node_modules/')
            ->toContain('/project/storage/framework/');
    });

    it('ends every path with a separator so a sibling directory cannot match it', function (): void {
        expect(SourceScope::noisePaths('/project'))->each->toEndWith(DIRECTORY_SEPARATOR);
    });
});
