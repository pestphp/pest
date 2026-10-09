<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ExternalSources;
use Pest\Plugins\Tia\Fingerprint;
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

    expect(ExternalSources::rootsFor($repository['project']))->toBe(['packages/'])
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

    expect(ExternalSources::rootsFor($repository['project']))->toBe([''])
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
        ->toBe(['packages/shared/bootstrap.php']);
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

it('selects the phpunit file of the project root', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit/>
XML_WRAP);

    $previous = getcwd();
    chdir($repository['project']);

    try {
        $withoutArguments = ExternalSources::selectedConfiguration($repository['project'], []);
        $withArgument = ExternalSources::selectedConfiguration($repository['project'], ['-c', 'phpunit.xml']);
    } finally {
        chdir((string) $previous);
    }

    $expected = realpath($repository['project'].'/phpunit.xml');

    expect($withoutArguments)->toBe($expected)
        ->and($withArgument)->toBe($expected);
})->skipOnWindows();

it('tells the dist configuration apart from the one phpunit selects by default', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit/>
XML_WRAP);

    file_put_contents($repository['project'].'/phpunit.xml.dist', <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    $default = Fingerprint::compute($repository['project'], []);
    $dist = Fingerprint::compute($repository['project'], ['-c', 'phpunit.xml.dist']);

    expect($default['structural']['configuration'])->toStartWith('backend/phpunit.xml:')
        ->and($dist['structural']['configuration'])->toStartWith('backend/phpunit.xml.dist:')
        ->and(Fingerprint::structuralMatches($default, $dist))->toBeFalse();
})->skipOnWindows();

it('tells a run without configuration apart from the default one', function (): void {
    $repository = tiaExternalRepository([], <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../packages/shared/bootstrap.php"/>
XML_WRAP);

    $default = Fingerprint::compute($repository['project'], []);
    $none = Fingerprint::compute($repository['project'], ['--no-configuration']);

    expect($default['structural']['configuration'])->toStartWith('backend/phpunit.xml:')
        ->and($none['structural']['configuration'])->toBe('none')
        ->and(Fingerprint::structuralMatches($default, $none))->toBeFalse();
})->skipOnWindows();

it('holds no configuration value for a project that holds no configuration file', function (): void {
    $repository = tiaExternalRepository();

    expect(Fingerprint::compute($repository['project'], ['--no-configuration'])['structural']['configuration'])
        ->toBeNull();
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

it('reports the path of a configuration inside the repository', function (): void {
    $repository = tiaExternalRepository();

    expect(ExternalSources::repositoryRelative($repository['project'], $repository['project'].'/composer.json'))
        ->toBe('backend/composer.json')
        ->and(ExternalSources::repositoryRelative($repository['project'], sys_get_temp_dir()))
        ->toBeNull();
})->skipOnWindows();

it('tells two configurations apart when they hold the same bytes', function (): void {
    $repository = tiaExternalRepository();

    $xml = <<<'XML_WRAP'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
  <testsuites>
    <testsuite name="default">
      <directory suffix="Test.php">./tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML_WRAP;

    mkdir($repository['project'].'/config', 0755, true);
    file_put_contents($repository['project'].'/phpunit.ci.xml', $xml);
    file_put_contents($repository['project'].'/config/phpunit.ci.xml', $xml);

    $first = Fingerprint::compute($repository['project'], ['-c', $repository['project'].'/phpunit.ci.xml']);
    $second = Fingerprint::compute($repository['project'], ['-c', $repository['project'].'/config/phpunit.ci.xml']);

    expect($first['structural']['configuration'])->not->toBe($second['structural']['configuration'])
        ->and(Fingerprint::structuralMatches($first, $second))->toBeFalse();
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

    expect($roots)->toBe(['packages/'])
        ->and(ExternalSources::matching($repository['project'], ['packages/shared/src/Service.php']))
        ->toBe(['packages/shared/src/Service.php']);
})->skipOnWindows();

it('reports a root that the repository ignores as unverifiable', function (): void {
    $repository = tiaExternalRepository([
        'autoload' => ['psr-4' => ['Generated\\' => '../packages/generated']],
    ]);

    mkdir($repository['root'].'/packages/generated', 0755, true);
    file_put_contents($repository['root'].'/.gitignore', "packages/generated/\n");
    file_put_contents($repository['root'].'/packages/generated/Model.php', "<?php\n");

    ExternalSources::flush();

    expect(ExternalSources::rootsFor($repository['project']))->toBeEmpty()
        ->and(ExternalSources::unverifiable($repository['project']))
        ->toContain(str_replace(DIRECTORY_SEPARATOR, '/', (string) realpath($repository['root'])).'/packages/generated');
})->skipOnWindows();
