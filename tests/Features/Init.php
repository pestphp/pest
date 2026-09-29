<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('init creates a suite when the tests directory is missing', function (): void {
    $root = makeInitProject();

    try {
        $process = runInitProject($root, '--init');

        expect($process->getExitCode())->toBe(0)
            ->and($process->getErrorOutput())->not->toContain('cannot be resolved')
            ->and($process->getOutput())->toContain('File created.')
            ->and(is_file($root.DIRECTORY_SEPARATOR.'phpunit.xml'))->toBeTrue()
            ->and(is_file($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Pest.php'))->toBeTrue()
            ->and(is_file($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'TestCase.php'))->toBeTrue()
            ->and(is_file($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Unit'.DIRECTORY_SEPARATOR.'ExampleTest.php'))->toBeTrue();
    } finally {
        removeInitProject($root);
    }
});

test('a missing tests directory does not crash while resolving tia state', function (): void {
    $root = makeInitProject();

    try {
        $process = runInitProject($root, '--version');

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('does not exist')
            ->and($process->getErrorOutput())->not->toContain('cannot be resolved')
            ->and($process->getOutput())->not->toContain('cannot be resolved');
    } finally {
        removeInitProject($root);
    }
});

function makeInitProject(): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-init-'.bin2hex(random_bytes(4));

    mkdir($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin', 0777, true);

    copy(
        dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pest-plugins.json',
        $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pest-plugins.json',
    );

    $autoload = var_export(
        dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php',
        true,
    );

    file_put_contents($root.DIRECTORY_SEPARATOR.'run.php', <<<PHP
<?php

declare(strict_types=1);

use Pest\Kernel;
use Pest\Panic;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require {$autoload};

\$command = \$argv[1] ?? '--version';

\$GLOBALS['_composer_bin_dir'] = getcwd().'/vendor/bin';

\$_SERVER['argv'] = ['pest', \$command];
\$_SERVER['COLLISION_PRINTER'] = 'DefaultPrinter';

\$input = new ArgvInput(['pest', \$command]);
\$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, false);

\$testSuite = TestSuite::getInstance(getcwd(), 'tests');

try {
    \$kernel = Kernel::boot(\$testSuite, \$input, \$output);
    \$result = \$kernel->handle(\$_SERVER['argv'], \$_SERVER['argv']);
    \$kernel->terminate();
} catch (Throwable \$throwable) {
    Panic::with(\$throwable);
}

exit(\$result);
PHP);

    return $root;
}

function runInitProject(string $root, string $command): Process
{
    $process = new Process(
        [PHP_BINARY, 'run.php', $command],
        $root,
        [
            'PEST_NO_SUPPORT' => 'true',
            'COLLISION_PRINTER' => 'DefaultPrinter',
            'COLLISION_IGNORE_DURATION' => 'true',
        ],
    );

    $process->setTimeout(60);
    $process->run();

    return $process;
}

function removeInitProject(string $root): void
{
    if (! is_dir($root)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        if ($item->isDir() && ! $item->isLink()) {
            rmdir($item->getPathname());

            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($root);
}
