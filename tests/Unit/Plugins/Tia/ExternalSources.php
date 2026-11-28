<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Pest\Plugins\Tia\ExternalSources;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\GitRepo;

function tiaExternalCwd(?string $original = null): string
{
    static $cwd = '';

    if ($original !== null) {
        $cwd = $original;
    }

    return $cwd;
}

/**
 * @return array<int, string>
 */
function tiaExternalRoots(?string $created = null): array
{
    static $roots = [];

    if ($created !== null) {
        $roots[] = $created;

        return $roots;
    }

    $all = $roots;
    $roots = [];

    return $all;
}

/**
 * @param  array<string, mixed>  $manifest
 * @return array{root: string, project: string}
 */
function tiaExternalRepository(array $manifest = [], ?string $phpunit = null): array
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-external-'.bin2hex(random_bytes(8));

    mkdir($root.'/backend/app', 0755, true);
    mkdir($root.'/packages/shared/src', 0755, true);

    file_put_contents($root.'/backend/app/Service.php', "<?php\n\$service = 1;\n");
    file_put_contents($root.'/packages/shared/src/Shared.php', "<?php\n\$shared = 1;\n");
    file_put_contents($root.'/packages/shared/bootstrap.php', "<?php\n\$booted = 1;\n");
    file_put_contents($root.'/backend/composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

    if ($phpunit !== null) {
        file_put_contents($root.'/backend/phpunit.xml', $phpunit);
    }

    tiaExternalRoots($root);

    new GitRepo($root)->init('master');

    chdir($root.'/backend');
    ExternalSources::flush();

    return ['root' => $root, 'project' => $root.'/backend'];
}

/**
 * @param  array<int, string>  $arguments
 * @return array<int, string>
 */
function tiaRootsFrom(string $workingDirectory, string $projectRoot, array $arguments): array
{
    $previous = getcwd();

    chdir($workingDirectory);

    try {
        ExternalSources::flush();

        return ExternalSources::rootsFor($projectRoot, $arguments);
    } finally {
        chdir((string) $previous);
    }
}

/**
 * @param  array<int, string>  $arguments
 * @return array<int, string>
 */
function tiaUnverifiableFrom(string $workingDirectory, string $projectRoot, array $arguments): array
{
    $previous = getcwd();

    chdir($workingDirectory);

    try {
        ExternalSources::flush();

        return ExternalSources::unverifiable($projectRoot, $arguments);
    } finally {
        chdir((string) $previous);
    }
}

beforeEach(function (): void {
    putenv('COMPOSER');
    ExternalSources::flush();
    tiaExternalCwd((string) getcwd());
});

afterEach(function (): void {
    chdir(tiaExternalCwd());

    foreach (tiaExternalRoots() as $root) {
        new Process(['rm', '-rf', $root])->run();
    }

    ExternalSources::flush();
});

it('finds a psr-4 root that escapes the project', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['App\\' => 'app/', 'Shared\\' => '../packages/shared/src']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/src/']);
})->skipOnWindows();

it('finds a path repository', function (): void {
    $repository = tiaExternalRepository([
        'repositories' => [['type' => 'path', 'url' => '../packages/shared']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/']);
})->skipOnWindows();

it('finds a phpunit source directory outside the project', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">app</directory>
      <directory suffix=".php">../packages/shared/src</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['packages/shared/src/']);
})->skipOnWindows();

it('finds nothing when the project only loads its own code', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['App\\' => 'app/'], 'files' => ['app/Service.php']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBeEmpty();
})->skipOnWindows();

it('finds nothing for a project at the repository root', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Shared\\' => '../packages/shared/src']],
    ]);

    expect(ExternalSources::rootsFor($repository['root']))->toBeEmpty();
})->skipOnWindows();

it('reports a root that sits outside the repository as unverifiable', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Tmp\\' => '../../']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBeEmpty()
        ->and(ExternalSources::unverifiable($repository['project']))->not->toBeEmpty();
})->skipOnWindows();

it('reports nothing unverifiable when every declaration stays inside the repository', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Shared\\' => '../packages/shared/src', 'App\\' => 'app']],
    ]);

    expect(ExternalSources::unverifiable($repository['project']))->toBeEmpty();
})->skipOnWindows();

it('reports a bootstrap outside the repository as unverifiable', function (): void {
    $repository = tiaExternalRepository();

    $outside = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-outside-'.bin2hex(random_bytes(8)).'.php';
    file_put_contents($outside, "<?php\n");

    try {
        $unverifiable = tiaUnverifiableFrom($repository['project'], $repository['project'], ['--bootstrap', $outside]);
    } finally {
        @unlink($outside);
    }

    expect($unverifiable)->toBe([str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath(dirname($outside))).'/'.basename($outside)]);
})->skipOnWindows();

it('matches only the changes that fall under an external root', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Shared\\' => '../packages/shared/src']],
    ]);

    $matched = ExternalSources::matching($repository['project'], [
        'packages/shared/src/Shared.php',
        'packages/other/src/Other.php',
        'frontend/widget.php',
    ]);

    expect($matched)->toBe(['packages/shared/src/Shared.php']);
})->skipOnWindows();

it('finds an autoload file that escapes the project and matches it exactly', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['files' => ['../packages/shared/bootstrap.php']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/bootstrap.php'])
        ->and(ExternalSources::matching($repository['project'], ['packages/shared/bootstrap.php']))
        ->toBe(['packages/shared/bootstrap.php']);
})->skipOnWindows();

it('finds a phpunit bootstrap that escapes the project', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php">
  <source>
    <include>
      <directory suffix=".php">app</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['packages/shared/bootstrap.php']);
})->skipOnWindows();

it('keeps a declared root that no longer exists on disk', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Shared\\' => '../packages/shared/src']],
    ]);

    new Process(['rm', '-rf', $repository['root'].'/packages/shared'])->mustRun();

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/src/'])
        ->and(ExternalSources::matching($repository['project'], ['packages/shared/src/Shared.php']))
        ->toBe(['packages/shared/src/Shared.php']);
})->skipOnWindows();

it('treats a classmap entry as a file only when it names one', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['classmap' => ['../packages/shared/src', '../packages/shared/bootstrap.php']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))
        ->toBe(['packages/shared/bootstrap.php', 'packages/shared/src/']);
})->skipOnWindows();

it('finds a testsuite directory outside the project', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory suffix="Test.php">./tests</directory>
      <directory suffix="Test.php">../packages/shared/tests</directory>
      <file>../packages/shared/tests/OneTest.php</file>
    </testsuite>
  </testsuites>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe([
        'packages/shared/tests/',
        'packages/shared/tests/OneTest.php',
    ]);
})->skipOnWindows();

it('keeps the directory before a wildcard in a path repository', function (): void {
    $repository = tiaExternalRepository([
        'repositories' => [['type' => 'path', 'url' => '../packages/*/src']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/', 'packages/shared/src/'])
        ->and(ExternalSources::matching($repository['project'], ['packages/foo/src/Service.php']))
        ->toBe(['packages/foo/src/Service.php']);
})->skipOnWindows();

it('keeps the directory before a single character wildcard', function (): void {
    $repository = tiaExternalRepository([
        'repositories' => [['type' => 'path', 'url' => '../packages/lib-?/src']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/']);
})->skipOnWindows();

it('matches a deleted package that a wildcard declared', function (): void {
    $repository = tiaExternalRepository([
        'repositories' => [['type' => 'path', 'url' => '../packages/*']],
    ]);

    new Process(['rm', '-rf', $repository['root'].'/packages/shared'])->mustRun();

    ExternalSources::flush();

    expect(ExternalSources::matching($repository['project'], ['packages/shared/src/Shared.php']))
        ->toBe(['packages/shared/src/Shared.php']);
})->skipOnWindows();

it('covers the whole repository when a declaration reaches its root', function (): void {
    $repository = tiaExternalRepository([
        'repositories' => [['type' => 'path', 'url' => '../*']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['', 'packages/'])
        ->and(ExternalSources::matching($repository['project'], ['packages/shared/src/Shared.php']))
        ->toBe(['packages/shared/src/Shared.php']);
})->skipOnWindows();

it('reads the configuration that the command line selects', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['project'].'/phpunit.ci.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php">
  <testsuites>
    <testsuite name="default">
      <directory suffix="Test.php">../packages/shared/tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['--tia', '-c', 'phpunit.ci.xml']))->toBe([
        'packages/shared/bootstrap.php',
        'packages/shared/tests/',
    ])->and(tiaRootsFrom($repository['project'], $repository['project'], []))->toBeEmpty();
})->skipOnWindows();

it('accepts the configuration argument in its joined form', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['project'].'/phpunit.ci.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['--configuration=phpunit.ci.xml']))
        ->toBe(['packages/shared/bootstrap.php']);
})->skipOnWindows();

it('resolves a configuration path against its own directory', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/config', 0755, true);
    file_put_contents($repository['root'].'/config/phpunit.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['-c', '../config/phpunit.xml']))->toBe([
        'config/phpunit.xml',
        'packages/shared/bootstrap.php',
    ]);
})->skipOnWindows();

it('follows a symlink that leaves the project', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Shared\\' => 'shared']],
    ]);

    symlink($repository['root'].'/packages/shared/src', $repository['project'].'/shared');

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/src/'])
        ->and(ExternalSources::matching($repository['project'], ['packages/shared/src/Shared.php']))
        ->toBe(['packages/shared/src/Shared.php']);
})->skipOnWindows();

it('keeps a real directory inside the project out of the roots', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['App\\' => 'app']],
    ]);

    expect(ExternalSources::rootsFor($repository['project']))->toBeEmpty();
})->skipOnWindows();

it('finds a bootstrap file that the command line names', function (): void {
    $repository = tiaExternalRepository();

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['--bootstrap', '../packages/shared/bootstrap.php']))
        ->toBe(['packages/shared/bootstrap.php']);
})->skipOnWindows();

it('finds every directory that an include path names', function (): void {
    $repository = tiaExternalRepository();

    $list = '../packages/shared/src'.PATH_SEPARATOR.'app'.PATH_SEPARATOR.'../packages/shared/tests';

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['--include-path', $list]))
        ->toBe(['packages/shared/src/', 'packages/shared/tests/']);
})->skipOnWindows();

it('resolves a command line path against the directory the command ran in', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/phpunit.ci.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="packages/shared/bootstrap.php"/>
XML_WRAP);

    $previous = getcwd();
    chdir($repository['root']);

    try {
        $roots = ExternalSources::rootsFor($repository['project'], ['-c', 'phpunit.ci.xml']);
    } finally {
        chdir((string) $previous);
    }

    expect($roots)->toBe(['packages/shared/bootstrap.php', 'phpunit.ci.xml']);
})->skipOnWindows();

it('keeps a declaration that a commit deleted under the directory the command ran in', function (): void {
    $repository = tiaExternalRepository();

    new Process(['rm', '-rf', $repository['root'].'/packages'])->mustRun();

    expect(tiaRootsFrom($repository['root'], $repository['project'], ['--bootstrap', 'packages/shared/bootstrap.php']))
        ->toBe(['app/', 'packages/shared/bootstrap.php', 'src/', 'tests/', 'vendor/autoload.php']);
})->skipOnWindows();

it('watches the paths of the configuration Pest generates in the directory the command ran in', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/vendor', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', "/vendor/\n");
    file_put_contents($repository['root'].'/vendor/autoload.php', "<?php\n");

    $previous = getcwd();
    chdir($repository['root']);

    try {
        ExternalSources::flush();
        $inputs = ExternalSources::ignoredInputs($repository['project']);
    } finally {
        chdir((string) $previous);
    }

    expect($inputs)->toBe(['vendor/autoload.php']);
})->skipOnWindows();

it('treats a classmap directory that carries a dot as a directory', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['classmap' => ['../packages/shared.core']],
    ]);

    mkdir($repository['root'].'/packages/shared.core', 0755, true);

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared.core/'])
        ->and(ExternalSources::matching($repository['project'], ['packages/shared.core/Service.php']))
        ->toBe(['packages/shared.core/Service.php']);
})->skipOnWindows();

it('accepts the configuration argument attached to its short flag', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['project'].'/phpunit.ci.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['-cphpunit.ci.xml']))
        ->toBe(['packages/shared/bootstrap.php']);
})->skipOnWindows();

it('reads no configuration when the run asks for none', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))
        ->toBe(['packages/shared/bootstrap.php'])
        ->and(tiaRootsFrom($repository['project'], $repository['project'], ['--no-configuration']))
        ->toBeEmpty();
})->skipOnWindows();

it('reads no default configuration when the command line names one', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    file_put_contents($repository['project'].'/phpunit.ci.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit/>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['-c', 'phpunit.ci.xml']))
        ->toBeEmpty();
})->skipOnWindows();

it('finds an include path that the configuration names', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <php>
    <includePath>../packages/shared/src</includePath>
    <includePath>app</includePath>
  </php>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))
        ->toBe(['packages/shared/src/']);
})->skipOnWindows();

it('matches a submodule that a commit moved', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Shared\\' => '../packages/shared/src']],
    ]);

    expect(ExternalSources::matching($repository['project'], ['packages/shared']))
        ->toBe(['packages/shared'])
        ->and(ExternalSources::matching($repository['project'], ['packages']))
        ->toBe(['packages'])
        ->and(ExternalSources::matching($repository['project'], ['packages/other']))
        ->toBeEmpty();
})->skipOnWindows();

it('matches a submodule that holds a declared file', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['files' => ['../packages/shared/bootstrap.php']],
    ]);

    expect(ExternalSources::matching($repository['project'], ['packages/shared']))
        ->toBe(['packages/shared']);
})->skipOnWindows();

it('reads the configuration of the directory the command ran in', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    file_put_contents($repository['root'].'/phpunit.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="packages/shared/src/Shared.php"/>
XML_WRAP);

    expect(tiaRootsFrom($repository['root'], $repository['project'], []))
        ->toBe(['packages/shared/src/Shared.php', 'phpunit.xml']);
})->skipOnWindows();

it('reads a configuration that a directory argument names', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/config', 0755, true);
    file_put_contents($repository['root'].'/config/phpunit.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['-c', '../config']))
        ->toBe(['config/phpunit.xml', 'packages/shared/bootstrap.php']);
})->skipOnWindows();

it('reads the dist name that phpunit tries before the older one', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['project'].'/phpunit.dist.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))
        ->toBe(['packages/shared/bootstrap.php']);
})->skipOnWindows();

it('finds a bootstrap that a single test suite names', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <testsuites>
    <testsuite name="unit">
      <directory suffix="Test.php">./tests</directory>
    </testsuite>
    <testsuite name="integration" bootstrap="../packages/shared/bootstrap.php">
      <directory suffix="Test.php">./tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))
        ->toBe(['packages/shared/bootstrap.php']);
})->skipOnWindows();

it('watches the directory before a wildcard that a configuration names', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">../packages/*/src</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    $roots = tiaRootsFrom($repository['project'], $repository['project'], []);

    expect($roots)->toBe(['packages/', 'packages/shared/src/'])
        ->and(ExternalSources::matching($repository['project'], ['packages/shared/src/Service.php']))
        ->toBe(['packages/shared/src/Service.php']);
})->skipOnWindows();

it('lists the files of a root that the repository ignores as ignored inputs', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Generated\\' => '../packages/generated']],
    ]);

    mkdir($repository['root'].'/packages/generated', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', "packages/generated/\n");
    file_put_contents($repository['root'].'/packages/generated/Model.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBeEmpty()
        ->and(ExternalSources::unverifiable($repository['project']))->toBeEmpty()
        ->and(ExternalSources::ignoredInputs($repository['project']))->toBe(['packages/generated/Model.php']);
})->skipOnWindows();

it('lists the ignored files under a declared root as ignored inputs', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "packages/shared/generated.php\npackages/shared/notes.txt\npackages/shared/vendor/\npackages/shared/node_modules/\npackages/shared/storage/\n");
    file_put_contents($repository['root'].'/packages/shared/generated.php', "<?php\n");
    file_put_contents($repository['root'].'/packages/shared/notes.txt', "notes\n");
    mkdir($repository['root'].'/packages/shared/vendor', 0755, true);
    file_put_contents($repository['root'].'/packages/shared/vendor/autoload.php', "<?php\n");
    mkdir($repository['root'].'/packages/shared/node_modules/tool', 0755, true);
    file_put_contents($repository['root'].'/packages/shared/node_modules/tool/index.php', "<?php\n");
    file_put_contents($repository['root'].'/packages/shared/.DS_Store', "finder\n");
    mkdir($repository['root'].'/packages/shared/storage/logs', 0755, true);
    file_put_contents($repository['root'].'/packages/shared/storage/logs/test.log', "log\n");

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project'], ['--include-path', '../packages/shared']))->toBe(['packages/shared/generated.php', 'packages/shared/notes.txt'])
        ->and(ExternalSources::unverifiable($repository['project'], ['--include-path', '../packages/shared']))->toBeEmpty();
})->skipOnWindows();

it('lists an ignored bootstrap file inside vendor as an ignored input', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../vendor/autoload.php"/>
XML_WRAP);

    mkdir($repository['root'].'/vendor', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', "vendor/\n");
    file_put_contents($repository['root'].'/vendor/autoload.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project']))->toBe(['vendor/autoload.php']);
})->skipOnWindows();

it('lists the ignored files under a declared root inside a dot directory', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/.shared/.cache', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', ".shared/\n");
    file_put_contents($repository['root'].'/.shared/Shared.php', "<?php\n");
    file_put_contents($repository['root'].'/.shared/.cache/state', "cache\n");

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project'], ['--include-path', '../.shared']))->toBe(['.shared/Shared.php']);
})->skipOnWindows();

it('lists an ignored link to a file but no ignored link to a directory or to nothing', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/packages/shared/storage', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', "packages/shared/public/\n");
    mkdir($repository['root'].'/packages/shared/public', 0755, true);
    symlink('../storage', $repository['root'].'/packages/shared/public/storage');
    symlink('../missing', $repository['root'].'/packages/shared/public/dangling');
    symlink('../src/Shared.php', $repository['root'].'/packages/shared/public/shared.php');

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project'], ['--include-path', '../packages/shared']))->toBe(['packages/shared/public/shared.php']);
})->skipOnWindows();

it('watches the path that composer resolved for a path repository', function (): void {
    $repository = tiaExternalRepository([
        'repositories' => [['type' => 'path', 'url' => '$PEST_TIA_SHARED']],
    ]);

    file_put_contents($repository['project'].'/composer.lock', (string) json_encode([
        'packages' => [['name' => 'acme/shared', 'dist' => ['type' => 'path', 'url' => '../packages/shared']]],
        'packages-dev' => [],
    ]));

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/']);
})->skipOnWindows();

it('watches the path that composer installed when the project has no lock file', function (): void {
    $repository = tiaExternalRepository([
        'repositories' => [['type' => 'path', 'url' => '../packages/{shared,other}']],
    ]);

    mkdir($repository['project'].'/vendor/composer', 0755, true);
    file_put_contents($repository['project'].'/vendor/composer/installed.json', (string) json_encode([
        'packages' => [['name' => 'acme/shared', 'dist' => ['type' => 'path', 'url' => '../packages/shared']]],
    ]));

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toContain('packages/shared/');
})->skipOnWindows();

it('watches the path that composer installed into a custom vendor directory', function (): void {
    $repository = tiaExternalRepository([
        'config' => ['vendor-dir' => 'deps'],
        'repositories' => [['type' => 'path', 'url' => '$PEST_TIA_SHARED']],
    ]);

    mkdir($repository['project'].'/deps/composer', 0755, true);
    file_put_contents($repository['project'].'/deps/composer/installed.json', (string) json_encode([
        'packages' => [['name' => 'acme/shared', 'dist' => ['type' => 'path', 'url' => '../packages/shared']]],
    ]));

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/']);
})->skipOnWindows();

it('watches the path that composer installed into the vendor directory the run loaded', function (): void {
    $repository = tiaExternalRepository();

    $vendor = $repository['project'].'/lib/deps';
    mkdir($vendor.'/composer', 0755, true);
    file_put_contents($vendor.'/composer/installed.php', "<?php\n\nreturn ['root' => ['install_path' => ".var_export($repository['project'], true)."]];\n");
    file_put_contents($vendor.'/composer/installed.json', (string) json_encode([
        'packages' => [['name' => 'acme/shared', 'dist' => ['type' => 'path', 'url' => '../packages/shared']]],
    ]));

    $loader = new ClassLoader($vendor);
    $loader->register();

    try {
        ExternalSources::flush();

        expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/']);
    } finally {
        $loader->unregister();
    }
})->skipOnWindows();

it('watches the installed path and the locked path when they differ', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/packages/old', 0755, true);
    mkdir($repository['project'].'/vendor/composer', 0755, true);
    file_put_contents($repository['project'].'/composer.lock', (string) json_encode([
        'packages' => [['name' => 'acme/shared', 'dist' => ['type' => 'path', 'url' => '../packages/shared']]],
    ]));
    file_put_contents($repository['project'].'/vendor/composer/installed.json', (string) json_encode([
        'packages' => [['name' => 'acme/shared', 'dist' => ['type' => 'path', 'url' => '../packages/old']]],
    ]));

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/old/', 'packages/shared/']);
})->skipOnWindows();

it('reads a path that composer resolved as a literal path', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/packages/odd?', 0755, true);
    file_put_contents($repository['project'].'/composer.lock', (string) json_encode([
        'packages' => [['name' => 'acme/odd', 'dist' => ['type' => 'path', 'url' => '../packages/odd?']]],
    ]));

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/odd?/']);
})->skipOnWindows();

it('reads the manifest and the lock file that the COMPOSER variable names', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['project'].'/composer.app.json', (string) json_encode([
        'autoload' => ['psr-4' => ['Shared\\' => '../packages/shared/src']],
    ]));
    file_put_contents($repository['project'].'/composer.app.lock', (string) json_encode([
        'packages' => [['name' => 'acme/odd', 'dist' => ['type' => 'path', 'url' => '../packages/odd']]],
    ]));
    mkdir($repository['root'].'/packages/odd', 0755, true);

    putenv('COMPOSER= composer.app.json ');

    try {
        ExternalSources::flush();

        expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/odd/', 'packages/shared/src/']);
    } finally {
        putenv('COMPOSER');
    }
})->skipOnWindows();

it('lists the ignored files when git is told to read every pathspec literally', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "packages/shared/generated.php\n");
    file_put_contents($repository['root'].'/packages/shared/generated.php', "<?php\n");

    putenv('GIT_LITERAL_PATHSPECS=1');
    $_SERVER['GIT_LITERAL_PATHSPECS'] = '1';

    try {
        ExternalSources::flush();

        expect(ExternalSources::ignoredInputs($repository['project'], ['--include-path', '../packages/shared']))->toBe(['packages/shared/generated.php']);
    } finally {
        putenv('GIT_LITERAL_PATHSPECS');
        unset($_SERVER['GIT_LITERAL_PATHSPECS']);
    }
})->skipOnWindows();

it('treats a network path as absolute on Windows', function (): void {
    $isAbsolute = new ReflectionMethod(ExternalSources::class, 'isAbsolute');

    expect($isAbsolute->invoke(null, '\\\\server\\share\\composer.app.json'))->toBeTrue();
})->skip(PHP_OS_FAMILY !== 'Windows', 'Network paths only exist on Windows.');

it('reports an ignored repository under a declared root as unverifiable', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "packages/shared/checkout/\n");
    mkdir($repository['root'].'/packages/shared/checkout', 0755, true);
    new GitRepo($repository['root'].'/packages/shared/checkout')->init('master');

    ExternalSources::flush();

    $root = str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath($repository['root']));

    expect(ExternalSources::unverifiable($repository['project'], ['--include-path', '../packages/shared']))->toBe([$root.'/packages/shared/checkout']);
})->skipOnWindows();

it('reports an ignored file under a declared root whose name is not utf-8 as unverifiable', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "packages/shared/*.txt\n");

    file_put_contents($repository['root']."/packages/shared/caf\xe9.txt", "x\n");
    file_put_contents($repository['root'].'/packages/shared/notes.txt', "x\n");

    ExternalSources::flush();

    $root = str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath($repository['root']));

    expect(ExternalSources::ignoredInputs($repository['project'], ['--include-path', '../packages/shared']))->toBe(['packages/shared/notes.txt'])
        ->and(ExternalSources::unverifiable($repository['project'], ['--include-path', '../packages/shared']))->toBe([$root."/packages/shared/caf\xe9.txt"]);
})->group('integration', 'tia')->skip(PHP_OS_FAMILY !== 'Linux', 'Only Linux file systems accept names that are not utf-8.');

it('finds a root that the repository ignores next to a file whose name looks like a pathspec', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['files' => ['../:(fixtures).php', '../packages/generated.php']],
    ]);

    file_put_contents($repository['root'].'/.gitignore', "packages/generated.php\n");
    file_put_contents($repository['root'].'/:(fixtures).php', "<?php\n");
    file_put_contents($repository['root'].'/packages/generated.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project']))->toBe(['packages/generated.php']);
})->skipOnWindows();

it('reports no repository that sits in a noise directory under a declared root as unverifiable', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "vendor/\n.tools/\n");
    mkdir($repository['root'].'/packages/shared/vendor/acme/pkg', 0755, true);
    mkdir($repository['root'].'/packages/shared/.tools/tool', 0755, true);
    new GitRepo($repository['root'].'/packages/shared/vendor/acme/pkg')->init('master');
    new GitRepo($repository['root'].'/packages/shared/.tools/tool')->init('master');

    ExternalSources::flush();

    expect(ExternalSources::unverifiable($repository['project'], ['--include-path', '../packages/shared']))->toBeEmpty();
})->skipOnWindows();

it('reports a repository under an inner declared root even when an outer root counts it as noise', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "vendor/\n");
    mkdir($repository['root'].'/packages/shared/vendor/acme/pkg', 0755, true);
    new GitRepo($repository['root'].'/packages/shared/vendor/acme/pkg')->init('master');

    ExternalSources::flush();

    $root = str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath($repository['root']));

    expect(ExternalSources::unverifiable($repository['project'], ['--include-path', '../packages/shared'.PATH_SEPARATOR.'../packages/shared/vendor']))
        ->toBe([$root.'/packages/shared/vendor/acme/pkg']);
})->skipOnWindows();

it('reports a root inside an ignored repository as unverifiable', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "packages/shared/checkout/\n");
    mkdir($repository['root'].'/packages/shared/checkout/src', 0755, true);
    file_put_contents($repository['root'].'/packages/shared/checkout/src/Shared.php', "<?php\n");
    new GitRepo($repository['root'].'/packages/shared/checkout')->init('master');

    ExternalSources::flush();

    $root = str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath($repository['root']));

    expect(ExternalSources::unverifiable($repository['project'], ['--include-path', '../packages/shared/checkout/src']))->toBe([$root.'/packages/shared/checkout/src']);
})->skipOnWindows();

it('reads a bootstrap whose name holds a wildcard character as a literal file', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/.bootstrap?.php"/>
XML_WRAP);

    file_put_contents($repository['root'].'/.gitignore', "packages/shared/.bootstrap?.php\n");
    file_put_contents($repository['root'].'/packages/shared/.bootstrap?.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project']))->toBe(['packages/shared/.bootstrap?.php']);
})->skipOnWindows();

it('reads a psr-4 directory whose name holds a wildcard character as a literal directory', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Shared\\' => '../packages/shared/.lib?/']],
    ]);

    mkdir($repository['root'].'/packages/shared/.lib?', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', "packages/shared/.lib?/Generated.php\n");
    file_put_contents($repository['root'].'/packages/shared/.lib?/Generated.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/.lib?/'])
        ->and(ExternalSources::ignoredInputs($repository['project']))->toBe(['packages/shared/.lib?/Generated.php']);
})->skipOnWindows();

it('reads an include path whose name holds a wildcard character as a literal directory', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <php>
    <includePath>../packages/shared/lib?</includePath>
  </php>
</phpunit>
XML_WRAP);

    mkdir($repository['root'].'/packages/shared/lib?', 0755, true);
    file_put_contents($repository['root'].'/packages/shared/lib?/Helper.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/shared/lib?/']);
})->skipOnWindows();

it('reads every path of an include path list', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <php>
    <includePath>../packages/shared/lib?:../packages/fallback</includePath>
  </php>
</phpunit>
XML_WRAP);

    mkdir($repository['root'].'/packages/shared/lib?', 0755, true);
    mkdir($repository['root'].'/packages/fallback', 0755, true);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['packages/fallback/', 'packages/shared/lib?/']);
})->skipOnWindows();

it('watches the configuration directory when an include path list starts with a separator', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/config', 0755, true);
    file_put_contents($repository['root'].'/config/phpunit.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <php>
    <includePath>:../packages/fallback</includePath>
  </php>
</phpunit>
XML_WRAP);
    mkdir($repository['root'].'/packages/fallback', 0755, true);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['--configuration', '../config/phpunit.xml']))->toContain('config/');
})->skipOnWindows();

it('watches nothing for an empty include path, as PHPUnit adds nothing', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['root'].'/config', 0755, true);
    file_put_contents($repository['root'].'/config/phpunit.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <php>
    <includePath></includePath>
  </php>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['--configuration', '../config/phpunit.xml']))->toBe(['config/phpunit.xml']);
})->skipOnWindows();

it('resolves every path of an include path list after the first one from the working directory', function (): void {
    $repository = tiaExternalRepository();

    mkdir($repository['project'].'/config', 0755, true);
    file_put_contents($repository['project'].'/config/phpunit.xml', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <php>
    <includePath>../../packages/shared:../packages/fallback</includePath>
  </php>
</phpunit>
XML_WRAP);
    mkdir($repository['root'].'/packages/fallback', 0755, true);

    expect(tiaRootsFrom($repository['project'], $repository['project'], ['--configuration', 'config/phpunit.xml']))->toBe(['packages/fallback/', 'packages/shared/']);
})->skipOnWindows();

it('watches the directory before a bracket and a brace path as it is', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">../packages/[st]hared/src</directory>
      <directory suffix=".php">../libraries/{a,b}</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    mkdir($repository['root'].'/libraries', 0755, true);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['libraries/{a,b}/', 'packages/', 'packages/shared/src/']);
})->skipOnWindows();

it('watches the directory an escaped brace names', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">../packages/\{local}/src</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    mkdir($repository['root'].'/packages/{local}/src', 0755, true);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['packages/', 'packages/{local}/src/']);
})->skipOnWindows();

it('watches every directory a double star pattern reaches like PHPUnit', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">../packages/**/.generated/src</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    mkdir($repository['root'].'/packages/one/.generated/src', 0755, true);
    mkdir($repository['root'].'/packages/team/lib/.generated/src', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', ".generated/\n");
    file_put_contents($repository['root'].'/packages/one/.generated/src/One.php', "<?php\n");
    file_put_contents($repository['root'].'/packages/team/lib/.generated/src/Helper.php', "<?php\n");

    expect(ExternalSources::ignoredInputs($repository['project']))->toBe([
        'packages/one/.generated/src/One.php',
        'packages/team/lib/.generated/src/Helper.php',
    ]);
})->skipOnWindows();

it('also watches a directory whose real name looks like a pattern', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">../packages/.generated{local}/src</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    mkdir($repository['root'].'/packages/.generated{local}/src', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', "packages/.generated{local}/src/Helper.php\n");
    file_put_contents($repository['root'].'/packages/.generated{local}/src/Helper.php', "<?php\n");

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['packages/.generated{local}/src/']);

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project']))->toBe(['packages/.generated{local}/src/Helper.php']);
})->skipOnWindows();

it('keeps an existing absolute directory with brackets in its path as it is', function (): void {
    $repository = tiaExternalRepository();

    $shared = str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath($repository['root'])).'/mono[dev]/shared';
    mkdir($shared, 0755, true);
    file_put_contents($repository['project'].'/phpunit.xml', <<<XML_WRAP
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <testsuites>
    <testsuite name="shared">
      <directory>{$shared}</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML_WRAP);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['mono[dev]/shared/'])
        ->and(ExternalSources::unverifiable($repository['project']))->toBeEmpty();
})->skipOnWindows();

it('watches the directories a bracket pattern matches even when its literal name also exists', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <source>
    <include>
      <directory suffix=".php">../packages/[st]hared/src</directory>
    </include>
  </source>
</phpunit>
XML_WRAP);

    mkdir($repository['root'].'/packages/[st]hared/src', 0755, true);

    expect(tiaRootsFrom($repository['project'], $repository['project'], []))->toBe(['packages/', 'packages/shared/src/']);
})->skipOnWindows();

it('cuts a path at a brace only when the glob of its reader expands braces', function (): void {
    $beforeWildcard = new ReflectionMethod(ExternalSources::class, 'beforeWildcard');

    expect($beforeWildcard->invoke(null, '../repo{dev,ci}/shared/Helper.php', 0))->toBe('../repo{dev,ci}/shared/Helper.php')
        ->and($beforeWildcard->invoke(null, '../repo{dev,ci}/shared/Helper.php', GLOB_BRACE))->toBe('..')
        ->and($beforeWildcard->invoke(null, '../packages/[st]hared/src', 0))->toBe('../packages');
})->skip(! defined('GLOB_BRACE'), 'This platform has no brace expansion.');

it('lists no ignored file of the project itself as an ignored input', function (): void {
    $repository = tiaExternalRepository();

    file_put_contents($repository['root'].'/.gitignore', "backend/cache.php\n");
    file_put_contents($repository['root'].'/backend/cache.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::ignoredInputs($repository['project'], ['--include-path', '..']))->toBeEmpty();
})->skipOnWindows();

it('keeps the roots of the run when a test changes the working directory', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    $atStart = ExternalSources::rootsFor($repository['project']);

    chdir($repository['project'].'/app');

    expect($atStart)->toBe(['packages/shared/bootstrap.php'])
        ->and(ExternalSources::rootsFor($repository['project']))->toBe($atStart);
})->skipOnWindows();

it('keeps the drive letter when it resolves a missing directory against a Windows base', function (): void {
    $lexicalPath = new ReflectionMethod(ExternalSources::class, 'lexicalPath');

    expect($lexicalPath->invoke(null, 'C:/mono/backend', '../shared/src'))->toBe('C:/mono/shared/src');
})->skip(PHP_OS_FAMILY !== 'Windows', 'Drive letters exist only on Windows.');

it('resolves a path that starts with a drive letter against its base outside Windows', function (): void {
    $isAbsolute = new ReflectionMethod(ExternalSources::class, 'isAbsolute');
    $lexicalPath = new ReflectionMethod(ExternalSources::class, 'lexicalPath');

    expect($isAbsolute->invoke(null, 'C:/fixture/bootstrap.php'))->toBeFalse()
        ->and($lexicalPath->invoke(null, '/repo/backend/config', 'C:/fixture/bootstrap.php'))->toBe('/repo/backend/config/C:/fixture/bootstrap.php');
})->skipOnWindows();

it('treats a path from the root of the current drive as absolute on Windows', function (): void {
    $isAbsolute = new ReflectionMethod(ExternalSources::class, 'isAbsolute');

    expect($isAbsolute->invoke(null, '\\mono\\shared\\src'))->toBeTrue();
})->skip(PHP_OS_FAMILY !== 'Windows', 'Paths from the root of the current drive exist only on Windows.');

it('keeps the current drive when it resolves a missing path from the root of that drive', function (): void {
    $lexicalPath = new ReflectionMethod(ExternalSources::class, 'lexicalPath');
    $drive = substr((string) getcwd(), 0, 2);

    expect($lexicalPath->invoke(null, $drive.'/mono/backend', '\\mono\\shared\\src'))->toBe($drive.'/mono/shared/src');
})->skip(PHP_OS_FAMILY !== 'Windows', 'Paths from the root of the current drive exist only on Windows.');

it('keeps the network share when it resolves a missing directory against a UNC base', function (): void {
    $lexicalPath = new ReflectionMethod(ExternalSources::class, 'lexicalPath');

    expect($lexicalPath->invoke(null, '//server/share/mono/backend', '../shared/src'))->toBe('//server/share/mono/shared/src');
})->skip(PHP_OS_FAMILY !== 'Windows', 'UNC paths exist only on Windows.');

it('reads a leading double slash as the root outside Windows', function (): void {
    $lexicalPath = new ReflectionMethod(ExternalSources::class, 'lexicalPath');

    expect($lexicalPath->invoke(null, '/usr/local/backend', '//usr/local/missing/src'))->toBe('/usr/local/missing/src');
})->skipOnWindows();
