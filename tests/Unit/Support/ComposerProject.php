<?php

declare(strict_types=1);

use Pest\Support\ComposerProject;
use Symfony\Component\Process\Process;

it('reads the project root that composer recorded', function (): void {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-composer-project-'.bin2hex(random_bytes(8));
    mkdir($root.'/app/lib/deps/composer', 0755, true);
    file_put_contents($root.'/app/lib/deps/composer/installed.php', "<?php\n\nreturn ['root' => ['install_path' => ".var_export($root.'/app', true)."]];\n");

    try {
        expect(ComposerProject::rootPath($root.'/app/lib/deps/autoload.php'))->toBe(realpath($root.'/app'));
    } finally {
        new Process(['rm', '-rf', $root])->run();
    }
})->skipOnWindows();

it('falls back to the parent of the vendor directory when composer recorded no install path', function (): void {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-composer-project-'.bin2hex(random_bytes(8));
    mkdir($root.'/vendor/composer', 0755, true);
    file_put_contents($root.'/vendor/composer/installed.php', "<?php\n\nreturn ['root' => ['name' => '__root__'], 'versions' => []];\n");

    try {
        expect(ComposerProject::rootPath($root.'/vendor/autoload.php'))->toBe(realpath($root));
    } finally {
        new Process(['rm', '-rf', $root])->run();
    }
})->skipOnWindows();
