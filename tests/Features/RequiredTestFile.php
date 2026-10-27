<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

afterEach(function (): void {
    if (! isset($this->requiredProject)) {
        return;
    }

    removeRequiredProject($this->requiredProject);
});

test('a required test file keeps its own cases and groups', function (): void {
    $root = makeRequiredProject(withHelper: true, withGamma: false);
    $this->requiredProject = $root;
    $junit = $root.DIRECTORY_SEPARATOR.'junit.xml';

    $result = runRequiredProject($root, '--colors=never', '--log-junit', $junit);

    expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toMatch('/Tests:\s+5 passed/');

    $files = junitFiles($junit);

    expect($files['alpha one'] ?? '')->toContain('AlphaTest.php')
        ->and($files['beta one'] ?? '')->toContain('BetaTest.php')
        ->and($files['beta two'] ?? '')->toContain('BetaTest.php')
        ->and($files['from helper'] ?? '')->toContain('ViaHelperTest.php')
        ->and($files['defined in pest php'] ?? '')->toContain('Pest.php');

    $excluded = runRequiredProject($root, '--colors=never', '--exclude-group=beta');

    expect($excluded->getExitCode())->toBe(0, $excluded->getOutput().$excluded->getErrorOutput())
        ->and($excluded->getOutput())->toContain('alpha one')
        ->and($excluded->getOutput())->not->toContain('No tests found')
        ->and($excluded->getOutput())->toMatch('/Tests:\s+3 passed/');
});

test('parallel workers do not run a required file once per requiring file', function (): void {
    $root = makeRequiredProject(withHelper: false, withGamma: true);
    $this->requiredProject = $root;

    $serial = runRequiredProject($root, '--colors=never');
    $parallel = runRequiredProject($root, '--colors=never', '--parallel', '--processes=2');

    expect($serial->getExitCode())->toBe(0, $serial->getOutput().$serial->getErrorOutput())
        ->and($serial->getOutput())->toMatch('/Tests:\s+4 passed/')
        ->and($parallel->getExitCode())->toBe(0, $parallel->getOutput().$parallel->getErrorOutput())
        ->and($parallel->getOutput())->toMatch('/Tests:\s+4 passed/');
});

function makeRequiredProject(bool $withHelper, bool $withGamma): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-required-'.bin2hex(random_bytes(4));

    mkdir($root.DIRECTORY_SEPARATOR.'tests', 0777, true);
    mkdir($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin', 0777, true);

    $vendorAutoload = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

    copy(
        dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pest-plugins.json',
        $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'pest-plugins.json',
    );

    file_put_contents(
        $root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php',
        '<?php'.PHP_EOL.'return require '.var_export($vendorAutoload, true).';'.PHP_EOL,
    );

    file_put_contents($root.DIRECTORY_SEPARATOR.'phpunit.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         cacheDirectory=".phpunit.cache"
         colors="false"
         failOnRisky="true"
         failOnWarning="false"
>
  <testsuites>
    <testsuite name="default">
      <directory suffix=".php">./tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML);

    $helper = $withHelper ? <<<'PHP'
function helperTest(string $description, Closure $closure): void
{
    test($description, $closure);
}

test('defined in pest php', function (): void {
    expect(true)->toBeTrue();
});
PHP : '';

    file_put_contents($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Pest.php', "<?php\n\ndeclare(strict_types=1);\n\n".$helper);

    file_put_contents($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'AlphaTest.php', <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__.'/BetaTest.php';

test('alpha one', function (): void {
    expect(true)->toBeTrue();
});
PHP);

    file_put_contents($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'BetaTest.php', <<<'PHP'
<?php

declare(strict_types=1);

pest()->group('beta');

test('beta one', function (): void {
    expect(true)->toBeTrue();
});

test('beta two', function (): void {
    expect(true)->toBeTrue();
});
PHP);

    if ($withHelper) {
        file_put_contents($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'ViaHelperTest.php', <<<'PHP'
<?php

declare(strict_types=1);

helperTest('from helper', function (): void {
    expect(true)->toBeTrue();
});
PHP);
    }

    if ($withGamma) {
        file_put_contents($root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'GammaTest.php', <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__.'/BetaTest.php';

test('gamma one', function (): void {
    expect(true)->toBeTrue();
});
PHP);
    }

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

\$arguments = array_slice(\$argv, 1);
\$GLOBALS['_composer_bin_dir'] = getcwd().'/vendor/bin';
\$_SERVER['argv'] = ['pest', ...\$arguments];
\$_SERVER['COLLISION_PRINTER'] = 'DefaultPrinter';

\$input = new ArgvInput(\$_SERVER['argv']);
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

function runRequiredProject(string $root, string ...$command): Process
{
    $process = new Process(
        [PHP_BINARY, 'run.php', ...$command],
        $root,
        [
            'PEST_NO_SUPPORT' => 'true',
            'COLLISION_PRINTER' => 'DefaultPrinter',
            'COLLISION_IGNORE_DURATION' => 'true',
        ],
    );

    $process->setTimeout(120);
    $process->run();

    return $process;
}

/**
 * @return array<string, string>
 */
function junitFiles(string $path): array
{
    $xml = simplexml_load_string((string) file_get_contents($path));

    if ($xml === false) {
        return [];
    }

    $files = [];

    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $files[(string) $case['name']] = (string) $case['file'];
    }

    return $files;
}

function removeRequiredProject(string $root): void
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
