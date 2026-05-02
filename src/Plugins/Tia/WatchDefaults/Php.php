<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\WatchDefaults;

use Pest\Plugins\Tia\Contracts\WatchDefault;

/**
 * @internal
 */
final readonly class Php implements WatchDefault
{
    public function applicable(): bool
    {
        return true;
    }

    public function defaults(string $projectRoot, string $testPath): array
    {
        return [
            '.env' => [$testPath],
            '.env.testing' => [$testPath],
            '.env.local' => [$testPath],
            '.env.*.local' => [$testPath],

            'docker-compose.yml' => [$testPath],
            'docker-compose.yaml' => [$testPath],

            'phpunit.xml*' => [$testPath],

            $testPath.'/Fixtures/**/*' => [$testPath],
            $testPath.'/**/Fixtures/**/*' => [$testPath],

            $testPath.'/.pest/snapshots/**/*.snap' => [$testPath],
        ];
    }
}
