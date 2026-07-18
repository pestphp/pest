<?php

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

function tiaViteHelperPath(): string
{
    return dirname(__DIR__, 4).'/bin/pest-tia-vite-deps.mjs';
}

function tiaJson(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function tiaStripFixtures(): array
{
    return [
        'plain-object' => ['{"a":1,"b":2}', ['a' => 1, 'b' => 2]],
        'plain-array' => ['[1,2,3]', [1, 2, 3]],
        'nested' => ['{"a":{"b":[1,2]}}', ['a' => ['b' => [1, 2]]]],
        'empty-object' => ['{}', []],
        'empty-array' => ['[]', []],

        'line-comment-own-line' => ["{\n// comment\n\"a\":1\n}", ['a' => 1]],
        'line-comment-trailing' => ["{\"a\":1 // trailing\n}", ['a' => 1]],
        'leading-line-comments' => ["// a\n// b\n{\"a\":1}", ['a' => 1]],
        'line-comment-eof-no-newline' => ['{"a":1}// end', ['a' => 1]],

        'block-comment-leading' => ['{/* c */"a":1}', ['a' => 1]],
        'block-comment-inline' => ['{"a":1,/* c */"b":2}', ['a' => 1, 'b' => 2]],
        'block-comment-multiline' => ["{\n/* line1\nline2 */\n\"a\":1\n}", ['a' => 1]],
        'block-comment-eof' => ['{"a":1}/* end */', ['a' => 1]],
        'jsdoc-style-block' => ["{\n/**\n * doc\n */\n\"a\":1\n}", ['a' => 1]],
        'block-contains-double-slash' => ['{/* // inside */"a":1}', ['a' => 1]],
        'line-then-block' => ["{// l\n/* b */\"a\":1}", ['a' => 1]],
        'comment-with-quotes' => ['{/* "b":2 */"a":1}', ['a' => 1]],
        'comment-with-braces-commas' => ['{"a":1 /* },{ */,"b":2}', ['a' => 1, 'b' => 2]],

        'trailing-comma-object' => ['{"a":1,}', ['a' => 1]],
        'trailing-comma-array' => ['[1,2,]', [1, 2]],
        'trailing-comma-nested' => ['{"a":[1,2,],"b":{"c":3,},}', ['a' => [1, 2], 'b' => ['c' => 3]]],
        'trailing-comma-newline' => ["{\n\"a\":1,\n}", ['a' => 1]],
        'trailing-comma-then-block' => ['{"a":1, /* x */ }', ['a' => 1]],
        'trailing-comma-then-line' => ["{\"a\":1, // x\n}", ['a' => 1]],

        'crlf-line-comment' => ["{\r\n\"a\":1 // c\r\n}", ['a' => 1]],
        'tabs-whitespace' => ["{\n\t\"a\":\t1\n}", ['a' => 1]],

        'url-in-string' => [tiaJson(['u' => 'https://example.com//x']), ['u' => 'https://example.com//x']],
        'glob-in-string' => [tiaJson(['g' => 'resources/js/**/*.ts']), ['g' => 'resources/js/**/*.ts']],
        'path-alias-in-string' => [tiaJson(['p' => '@/*']), ['p' => '@/*']],
        'block-open-in-string' => [tiaJson(['s' => 'a/*b']), ['s' => 'a/*b']],
        'block-close-in-string' => [tiaJson(['s' => 'b*/c']), ['s' => 'b*/c']],
        'block-both-in-string' => [tiaJson(['s' => 'x/*y*/z']), ['s' => 'x/*y*/z']],
        'star-in-string' => [tiaJson(['s' => '5 * 3']), ['s' => '5 * 3']],
        'single-slashes-in-string' => [tiaJson(['s' => 'a/b/c']), ['s' => 'a/b/c']],

        'escaped-quote-in-string' => [tiaJson(['s' => 'a"b']), ['s' => 'a"b']],
        'escaped-backslash-in-string' => [tiaJson(['s' => 'a\\']), ['s' => 'a\\']],
        'backslash-then-slash' => [tiaJson(['s' => 'a\\/b']), ['s' => 'a\\/b']],
        'newline-escape-in-string' => [tiaJson(['s' => "a\nb"]), ['s' => "a\nb"]],
        'unicode-in-string' => [tiaJson(['s' => 'café ☕']), ['s' => 'café ☕']],

        'block-secret' => ['{"a":1/*SECRET*/}', ['a' => 1]],
    ];
}

function tiaStripResults(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $payload = [];
    foreach (tiaStripFixtures() as $name => [$raw]) {
        $payload[] = ['name' => $name, 'raw' => $raw];
    }

    $inputFile = tempnam(sys_get_temp_dir(), 'tia-strip-');
    file_put_contents($inputFile, json_encode($payload));

    $helper = str_replace('\\', '/', tiaViteHelperPath());
    $input = str_replace('\\', '/', $inputFile);

    $script = <<<JS
    import { stripJsonComments } from '{$helper}'
    import { readFileSync } from 'node:fs'
    const cases = JSON.parse(readFileSync('{$input}', 'utf8'))
    const out = {}
    for (const c of cases) {
      const stripped = stripJsonComments(c.raw)
      const entry = { stripped }
      try { entry.parsed = JSON.parse(stripped); entry.ok = true }
      catch (e) { entry.ok = false; entry.error = String(e && e.message ? e.message : e) }
      out[c.name] = entry
    }
    process.stdout.write(JSON.stringify(out))
    JS;

    $process = new Process(['node', '--input-type=module', '-e', $script]);
    $process->mustRun();

    @unlink($inputFile);

    return $cache = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

function tiaAliasFixtures(): array
{
    $config = fn (array $compilerOptions): array => ['tsconfig.json' => tiaJson(['compilerOptions' => $compilerOptions])];

    return [
        'basic' => [
            $config(['baseUrl' => '.', 'paths' => ['@/*' => ['./resources/js/*']]]),
            ['@' => 'resources/js'],
        ],
        'multiple-aliases' => [
            $config(['baseUrl' => '.', 'paths' => ['@/*' => ['./resources/js/*'], '~/*' => ['./src/*']]]),
            ['@' => 'resources/js', '~' => 'src'],
        ],
        'default-baseUrl' => [
            $config(['paths' => ['@/*' => ['./resources/js/*']]]),
            ['@' => 'resources/js'],
        ],
        'baseUrl-subdir' => [
            $config(['baseUrl' => 'src', 'paths' => ['@/*' => ['lib/*']]]),
            ['@' => 'src/lib'],
        ],
        'jsconfig-fallback' => [
            ['jsconfig.json' => tiaJson(['compilerOptions' => ['baseUrl' => '.', 'paths' => ['@/*' => ['./resources/js/*']]]])],
            ['@' => 'resources/js'],
        ],
        'first-target-wins' => [
            $config(['baseUrl' => '.', 'paths' => ['@/*' => ['./a/*', './b/*']]]),
            ['@' => 'a'],
        ],
        'multiple-keys-mixed' => [
            $config(['baseUrl' => '.', 'paths' => [
                '@/*' => ['./resources/js/*'],
                'bad' => ['./x/*'],
                '~/*' => ['./nope'],
            ]]),
            ['@' => 'resources/js'],
        ],
        'no-paths' => [
            $config(['baseUrl' => '.']),
            [],
        ],
        'no-compiler-options' => [
            ['tsconfig.json' => '{}'],
            [],
        ],
        'key-without-glob' => [
            $config(['baseUrl' => '.', 'paths' => ['@' => ['./resources/js/*']]]),
            [],
        ],
        'target-without-glob' => [
            $config(['baseUrl' => '.', 'paths' => ['@/*' => ['./resources/js']]]),
            [],
        ],
        'target-not-array' => [
            $config(['baseUrl' => '.', 'paths' => ['@/*' => './resources/js/*']]),
            [],
        ],
        'target-empty-array' => [
            $config(['baseUrl' => '.', 'paths' => ['@/*' => []]]),
            [],
        ],
        'commented-tsconfig' => [
            ['tsconfig.json' => <<<'JSON'
            {
                "compilerOptions": {
                    /* Visit https://aka.ms/tsconfig to read more. */
                    "target": "ESNext", // language target
                    "baseUrl": ".",
                    "paths": {
                        "@/*": ["./resources/js/*"],
                    },
                },
            }
            JSON],
            ['@' => 'resources/js'],
        ],
        'trailing-comma-paths' => [
            ['tsconfig.json' => '{"compilerOptions":{"baseUrl":".","paths":{"@/*":["./resources/js/*",],},},}'],
            ['@' => 'resources/js'],
        ],
        'malformed-json' => [
            ['tsconfig.json' => '{ this is not json'],
            [],
        ],
        'missing-config' => [
            [],
            [],
        ],
        'tsconfig-precedence' => [
            [
                'tsconfig.json' => tiaJson(['compilerOptions' => ['baseUrl' => '.', 'paths' => ['@/*' => ['./a/*']]]]),
                'jsconfig.json' => tiaJson(['compilerOptions' => ['baseUrl' => '.', 'paths' => ['@/*' => ['./b/*']]]]),
            ],
            ['@' => 'a'],
        ],
    ];
}

function tiaAliasResults(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $roots = [];
    $written = [];
    $payload = [];

    foreach (tiaAliasFixtures() as $name => [$files]) {
        $root = sys_get_temp_dir().'/pest-tia-'.bin2hex(random_bytes(6));
        mkdir($root, 0755, true);
        foreach ($files as $fname => $content) {
            file_put_contents($root.'/'.$fname, $content);
            $written[] = $root.'/'.$fname;
        }
        $root = str_replace('\\', '/', $root);
        $roots[$name] = $root;
        $payload[] = ['name' => $name, 'root' => $root];
    }

    $inputFile = tempnam(sys_get_temp_dir(), 'tia-alias-');
    file_put_contents($inputFile, json_encode($payload));

    $helper = str_replace('\\', '/', tiaViteHelperPath());
    $input = str_replace('\\', '/', $inputFile);

    $script = <<<JS
    import { loadAliasFromTsconfig } from '{$helper}'
    import { readFileSync } from 'node:fs'
    const cases = JSON.parse(readFileSync('{$input}', 'utf8'))
    const out = {}
    for (const c of cases) out[c.name] = await loadAliasFromTsconfig(c.root)
    process.stdout.write(JSON.stringify(out))
    JS;

    $process = new Process(['node', '--input-type=module', '-e', $script]);
    $process->mustRun();

    $aliases = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    @unlink($inputFile);
    foreach ($written as $f) {
        @unlink($f);
    }
    foreach ($roots as $r) {
        @rmdir($r);
    }

    return $cache = ['roots' => $roots, 'aliases' => $aliases];
}

function tiaViteAliasFixtures(): array
{
    return [
        'root-slash-literal' => [
            ['vite.config.js' => "export default { resolve: { alias: { '@': '/resources/js' } } }"],
            ['@' => 'resources/js'],
        ],
        'path-resolve' => [
            ['vite.config.ts' => "export default { resolve: { alias: { '@': path.resolve(__dirname, 'resources/js') } } }"],
            ['@' => 'resources/js'],
        ],
        'file-url' => [
            ['vite.config.mjs' => "export default { resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } } }"],
            ['@' => 'resources/js'],
        ],
        'tilde-alias' => [
            ['vite.config.js' => "export default { resolve: { alias: { '~': path.resolve(__dirname, 'resources') } } }"],
            ['~' => 'resources'],
        ],
        'multiple-aliases' => [
            ['vite.config.js' => "export default { resolve: { alias: { '@': '/resources/js', '@css': '/resources/css' } } }"],
            ['@' => 'resources/js', '@css' => 'resources/css'],
        ],
        'laravel-plugin-default' => [
            [
                'vite.config.js' => "import laravel from 'laravel-vite-plugin'\nexport default { plugins: [laravel({ input: ['resources/js/app.ts'] })] }",
                'package.json' => tiaJson(['devDependencies' => ['laravel-vite-plugin' => '^1.0']]),
            ],
            ['@' => 'resources/js'],
        ],
        'no-alias-no-plugin' => [
            [
                'vite.config.js' => 'export default { plugins: [] }',
                'package.json' => tiaJson(['devDependencies' => ['vue' => '^3.0']]),
            ],
            [],
        ],
        'no-config' => [
            [],
            [],
        ],
    ];
}

function tiaViteAliasResults(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $roots = [];
    $written = [];
    $payload = [];

    foreach (tiaViteAliasFixtures() as $name => [$files]) {
        $root = sys_get_temp_dir().'/pest-tia-vite-'.bin2hex(random_bytes(6));
        mkdir($root, 0755, true);
        foreach ($files as $fname => $content) {
            file_put_contents($root.'/'.$fname, $content);
            $written[] = $root.'/'.$fname;
        }
        $root = str_replace('\\', '/', $root);
        $roots[$name] = $root;
        $payload[] = ['name' => $name, 'root' => $root];
    }

    $inputFile = tempnam(sys_get_temp_dir(), 'tia-vite-alias-');
    file_put_contents($inputFile, json_encode($payload));

    $helper = str_replace('\\', '/', tiaViteHelperPath());
    $input = str_replace('\\', '/', $inputFile);

    $script = <<<JS
    import { loadAliasFromViteConfig } from '{$helper}'
    import { readFileSync } from 'node:fs'
    const cases = JSON.parse(readFileSync('{$input}', 'utf8'))
    const out = {}
    for (const c of cases) out[c.name] = await loadAliasFromViteConfig(c.root)
    process.stdout.write(JSON.stringify(out))
    JS;

    $process = new Process(['node', '--input-type=module', '-e', $script]);
    $process->mustRun();

    $aliases = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    @unlink($inputFile);
    foreach ($written as $f) {
        @unlink($f);
    }
    foreach ($roots as $r) {
        @rmdir($r);
    }

    return $cache = ['roots' => $roots, 'aliases' => $aliases];
}

beforeEach(function (): void {
    if ((new ExecutableFinder)->find('node') === null) {
        $this->markTestSkipped('node is not available.');
    }
});

it('strips JSONC down to something JSON.parse accepts', function (string $name): void {
    $result = tiaStripResults()[$name];
    [, $expected] = tiaStripFixtures()[$name];

    expect($result['ok'])->toBeTrue(
        "[{$name}] did not parse after stripping: ".($result['error'] ?? 'unknown').PHP_EOL.
        'stripped: '.$result['stripped'],
    )
        ->and($result['parsed'])->toEqual($expected);
})->with(array_keys(tiaStripFixtures()));

it('never touches comment-looking sequences inside string values', function (): void {
    expect(tiaStripResults()['url-in-string']['stripped'])->toContain('https://example.com//x')
        ->and(tiaStripResults()['glob-in-string']['stripped'])->toContain('resources/js/**/*.ts')
        ->and(tiaStripResults()['block-both-in-string']['stripped'])->toContain('x/*y*/z');
});

it('removes the comment body entirely', function (): void {
    expect(tiaStripResults()['block-secret']['stripped'])->not->toContain('SECRET');
});

it('builds the expected alias map from a tsconfig', function (string $name): void {
    $results = tiaAliasResults();
    [, $expectedRelative] = tiaAliasFixtures()[$name];

    $root = $results['roots'][$name];
    $expected = [];
    foreach ($expectedRelative as $key => $relative) {
        $expected[$key] = $root.'/'.$relative;
    }

    expect($results['aliases'][$name])->toEqual($expected);
})->with(array_keys(tiaAliasFixtures()));

it('builds the expected alias map from a vite config', function (string $name): void {
    $results = tiaViteAliasResults();
    [, $expectedRelative] = tiaViteAliasFixtures()[$name];

    $root = $results['roots'][$name];
    $expected = [];
    foreach ($expectedRelative as $key => $relative) {
        $expected[$key] = $root.'/'.$relative;
    }

    expect($results['aliases'][$name])->toEqual($expected);
})->with(array_keys(tiaViteAliasFixtures()));
