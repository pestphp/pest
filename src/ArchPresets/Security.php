<?php

declare(strict_types=1);

namespace Pest\ArchPresets;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * @internal
 */
final class Security extends AbstractPreset
{
    /**
     * Executes the arch preset.
     */
    public function execute(): void
    {
        $this->eachUserNamespace(
            fn (Expectation $namespace): ArchExpectation => $namespace->not->toHaveFileSystemPermissions('0777'),
        );

        $this->expectations[] = expect([
            'md5',
            'sha1',
            'uniqid',
            'rand',
            'mt_rand',
            'tempnam',
            'str_shuffle',
            'shuffle',
            'array_rand',
            'eval',
            'exec',
            'shell_exec',
            'system',
            'passthru',
            'create_function',
            'unserialize',
            'extract',
            'parse_str',
            'mb_parse_str',
            'dl',
            'assert',
        ])->not->toBeUsed();
    }
}
