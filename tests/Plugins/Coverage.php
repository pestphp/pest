<?php

use FilesystemIterator;
use Pest\Exceptions\InvalidOption;
use Pest\Plugins\Coverage;
use Pest\Support\Coverage as SupportCoverage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

function writeShardCoverage(string $basePath, array $lineCoverage): string
{
    $testId = 'Tests\ShardCoverageTest::testSomething';

    $codeCoverage = new ProcessedCodeCoverageData;
    $codeCoverage->setLineCoverage(['Foo.php' => $lineCoverage]);
    $codeCoverage->setTestIds([$testId]);

    $serialized = serialize([
        'buildInformation' => [
            'timestamp' => 'Mon Jan 1 00:00:00 UTC 2026',
            'runtime' => [
                'name' => 'PCOV',
                'version' => '1.0.12',
                'vendorUrl' => 'https://github.com/krakjoe/pcov',
            ],
            'phpCodeCoverage' => [
                'version' => '14.4.1',
                'serializationFormat' => 4,
                'driverInformation' => ['name' => 'PCOV', 'version' => '1.0.12'],
            ],
        ],
        'basePath' => $basePath,
        'codeCoverage' => $codeCoverage,
        'testResults' => [$testId => ['size' => 'unknown', 'status' => 'unknown', 'time' => 0.0]],
    ]);

    return "<?php // phpunit/php-code-coverage serialization format 4\n"
        ."return unserialize(<<<'END_OF_COVERAGE'\n"
        .$serialized."\n"
        ."END_OF_COVERAGE\n);";
}

test('compute comparable coverage', function (float $givenValue, float $expectedValue): void {
    $plugin = new Coverage(new NullOutput);

    $comparableCoverage = (fn () => $this->computeComparableCoverage($givenValue))->call($plugin);

    expect($comparableCoverage)->toBe($expectedValue);
})->with([
    [0, 0],
    [0.5, 0.5],
    [1.0, 1.0],
    [32.51, 32.5],
    [32.12312321312312, 32.1],
    [32.53333333333333, 32.5],
    [32.57777771232132, 32.5],
    [100.0, 100.0],
]);

test('apply thresholds', function (float $coverage, ?float $min, ?float $exactly, int $expectedExitCode): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);
    $plugin->coverageMin = $min ?? 0.0;
    $plugin->coverageExactly = $exactly;

    $exitCode = (fn () => $this->applyThresholds($coverage))->call($plugin);

    expect($exitCode)->toBe($expectedExitCode);

    if ($expectedExitCode === 1) {
        expect($output->fetch())->toContain('FAIL');
    }
})->with([
    'min pass' => [91.5, 80.0, null, 0],
    'min fail' => [91.5, 95.0, null, 1],
    'exactly pass' => [91.5, null, 91.5, 0],
    'exactly fail' => [91.5, null, 95.0, 1],
]);

test('strip shard blocked report flags', function (array $args, array $expected, bool $expectWarn): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags($args))->call($plugin);

    expect($filtered)->toBe($expected);

    $expectWarn
        ? expect($output->fetch())->toContain('WARN')
        : expect($output->fetch())->toBeEmpty();
})->with([
    'inline value flag' => [['--coverage-html=out', '--compact'], ['--compact'], true],
    'space-separated flag' => [['--compact', '--coverage-clover', 'clover.xml'], ['--compact'], true],
    'jsonl flag' => [['--coverage-jsonl=out.jsonl', '--compact'], ['--compact'], true],
    'optional value flag keeps the next argument' => [['tests/Unit', '--coverage-text', 'tests/Feature'], ['tests/Unit', 'tests/Feature'], true],
    'no blocked flags' => [['--compact', '--stop-on-failure'], ['--compact', '--stop-on-failure'], false],
]);

test('apply thresholds returns 0 when coverage meets min', function (): void {
    $plugin = new Coverage(new NullOutput);
    $plugin->coverageMin = 80.0;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(0);
});

test('apply thresholds returns 1 and writes FAIL when coverage is below min', function (): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);
    $plugin->coverageMin = 95.0;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(1)
        ->and($output->fetch())->toContain('95.0')->toContain('91.5');
});

test('apply thresholds returns 0 when coverage matches exactly', function (): void {
    $plugin = new Coverage(new NullOutput);
    $plugin->coverageExactly = 91.5;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(0);
});

test('apply thresholds returns 1 and writes FAIL when coverage does not match exactly', function (): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);
    $plugin->coverageExactly = 95.0;

    $exitCode = (fn () => $this->applyThresholds(91.5))->call($plugin);

    expect($exitCode)->toBe(1)
        ->and($output->fetch())->toContain('95.0')->toContain('91.5');
});

test('parse threshold options sets coverageMin', function (): void {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--min=42.5']))->call($plugin);

    expect($plugin->coverageMin)->toBe(42.5);
});

test('parse threshold options sets coverageExactly', function (): void {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--exactly=75.0']))->call($plugin);

    expect($plugin->coverageExactly)->toBe(75.0);
});

test('parse threshold options sets showOnlyCovered', function (): void {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--only-covered']))->call($plugin);

    expect($plugin->showOnlyCovered)->toBeTrue();
});

test('parse threshold options ignores unrelated flags', function (): void {
    $plugin = new Coverage(new NullOutput);

    (fn () => $this->parseThresholdOptions(['--compact', '--verbose']))->call($plugin);

    expect($plugin->coverageMin)->toBe(0.0)
        ->and($plugin->coverageExactly)->toBeNull()
        ->and($plugin->showOnlyCovered)->toBeFalse();
});

test('detect shard parses equals format', function (): void {
    $plugin = new Coverage(new NullOutput);

    $result = (fn () => $this->detectShard(['--shard=2/5']))->call($plugin);

    expect($result)->toBe(['index' => 2, 'total' => 5]);
});

test('detect shard parses space format', function (): void {
    $plugin = new Coverage(new NullOutput);

    $result = (fn () => $this->detectShard(['--shard', '3/4']))->call($plugin);

    expect($result)->toBe(['index' => 3, 'total' => 4]);
});

test('detect shard returns null when absent', function (): void {
    $plugin = new Coverage(new NullOutput);

    $result = (fn () => $this->detectShard(['--compact', '--coverage']))->call($plugin);

    expect($result)->toBeNull();
});

test('detect shard rejects a malformed shard like the shard plugin does', function (): void {
    $plugin = new Coverage(new NullOutput);

    expect(fn () => (fn () => $this->detectShard(['--shard=bad']))->call($plugin))
        ->toThrow(InvalidOption::class);
});

test('detect shard rejects an index above the total', function (): void {
    $plugin = new Coverage(new NullOutput);

    expect(fn () => (fn () => $this->detectShard(['--shard=3/2']))->call($plugin))
        ->toThrow(InvalidOption::class);
});

test('has shards coverage flag detects --shards-coverage', function (): void {
    $plugin = new Coverage(new NullOutput);

    expect((fn () => $this->hasShardsCoverageFlag(['--shards-coverage']))->call($plugin))->toBeTrue()
        ->and((fn () => $this->hasShardsCoverageFlag(['--coverage']))->call($plugin))->toBeFalse();
});

test('pop shards coverage flags removes --shards-coverage and --clean', function (): void {
    $plugin = new Coverage(new NullOutput);

    $remaining = (fn () => $this->popShardsCoverageFlags(['--shards-coverage', '--min=80', '--clean']))->call($plugin);
    $isClean = (fn () => $this->shardsCoverageClean)->call($plugin);

    expect($remaining)->toBe(['--min=80'])
        ->and($isClean)->toBeTrue();
});

test('strip shard blocked report flags removes --coverage-html', function (): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags(['--coverage-html=out', '--compact']))->call($plugin);

    expect($filtered)->toBe(['--compact'])
        ->and($output->fetch())->toContain('WARN');
});

test('strip shard blocked report flags removes --coverage-clover as separate arg', function (): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags(['--compact', '--coverage-clover', 'clover.xml']))->call($plugin);

    expect($filtered)->toBe(['--compact'])
        ->and($output->fetch())->toContain('WARN');
});

test('strip shard blocked report flags keeps non-blocked flags without warning', function (): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $filtered = (fn () => $this->stripShardBlockedReportFlags(['--compact', '--stop-on-failure']))->call($plugin);

    expect($filtered)->toBe(['--compact', '--stop-on-failure'])
        ->and($output->fetch())->toBeEmpty();
});

test('getCoverageDir sits beside the single run report', function (): void {
    $plugin = new Coverage(new NullOutput);

    $path = (fn () => $this->getCoverageDir())->call($plugin);

    expect($path)->toEndWith('.temp'.DIRECTORY_SEPARATOR.'coverage')
        ->and(dirname($path))->toBe(dirname(SupportCoverage::getPath()));
});

test('mergeAndReportShardsCoverage returns null when the directory holds no shard file', function (): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-shards-'.uniqid();
    mkdir($dir, 0755, true);

    try {
        $result = (fn () => $this->mergeAndReportShardsCoverage($dir))->call($plugin);

        expect($result)->toBeNull()
            ->and($output->fetch())->toContain('ERROR');
    } finally {
        @rmdir($dir);
    }
});

test('mergeAndReportShardsCoverage fails instead of reporting a partial total', function (): void {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-shards-'.uniqid();
    mkdir($dir, 0755, true);

    $files = [
        '1-of-2.cov' => '<?php return 1;',
        '2-of-2.cov' => '<?php this is not valid php',
    ];

    foreach ($files as $name => $contents) {
        file_put_contents($dir.DIRECTORY_SEPARATOR.$name, $contents);
    }

    try {
        $output = new BufferedOutput;
        $plugin = new Coverage($output);

        $result = (fn () => $this->mergeAndReportShardsCoverage($dir))->call($plugin);

        expect($result)->toBeNull()
            ->and($output->fetch())->toContain('2-of-2.cov');
    } finally {
        foreach (array_keys($files) as $name) {
            @unlink($dir.DIRECTORY_SEPARATOR.$name);
        }

        @rmdir($dir);
    }
});

test('mergeAndReportShardsCoverage fails when a shard wrote no file', function (): void {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-shards-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.DIRECTORY_SEPARATOR.'1-of-3.cov', '<?php return 1;');

    try {
        $output = new BufferedOutput;
        $plugin = new Coverage($output);

        $result = (fn () => $this->mergeAndReportShardsCoverage($dir))->call($plugin);

        expect($result)->toBeNull()
            ->and($output->fetch())->toContain('shard 2, 3 of 3 wrote no coverage file');
    } finally {
        @unlink($dir.DIRECTORY_SEPARATOR.'1-of-3.cov');
        @rmdir($dir);
    }
});

test('mergeAndReportShardsCoverage fails when a file belongs to another number of shards', function (): void {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-shards-'.uniqid();
    mkdir($dir, 0755, true);

    $files = ['1-of-2.cov' => '<?php return 1;', '1-of-4.cov' => '<?php return 1;'];

    foreach ($files as $name => $contents) {
        file_put_contents($dir.DIRECTORY_SEPARATOR.$name, $contents);
    }

    try {
        $output = new BufferedOutput;
        $plugin = new Coverage($output);

        $result = (fn () => $this->mergeAndReportShardsCoverage($dir))->call($plugin);

        expect($result)->toBeNull()
            ->and($output->fetch())->toContain('belongs to a run of 4 shards, not 2');
    } finally {
        foreach (array_keys($files) as $name) {
            @unlink($dir.DIRECTORY_SEPARATOR.$name);
        }

        @rmdir($dir);
    }
});

test('mergeAndReportShardsCoverage merges two shards into one total', function (): void {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-shards-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.DIRECTORY_SEPARATOR.'Foo.php', "<?php\n\$a = 1;\n\$b = 2;\n");

    $files = [
        '1-of-2.cov' => writeShardCoverage($dir, [2 => [0 => 1], 3 => []]),
        '2-of-2.cov' => writeShardCoverage($dir, [2 => [0 => 1], 3 => [0 => 1]]),
    ];

    foreach ($files as $name => $contents) {
        file_put_contents($dir.DIRECTORY_SEPARATOR.$name, $contents);
    }

    try {
        $output = new BufferedOutput;
        $plugin = new Coverage($output);

        $result = (fn () => $this->mergeAndReportShardsCoverage($dir))->call($plugin);

        expect($result)->toBe(100.0)
            ->and($output->fetch())->toContain('Merging coverage from 2 shards');
    } finally {
        foreach (array_keys($files) as $name) {
            @unlink($dir.DIRECTORY_SEPARATOR.$name);
        }

        @unlink($dir.DIRECTORY_SEPARATOR.'Foo.php');
        @rmdir($dir);
    }
});

function deleteDirectory(string $directory): void
{
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    ) as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }

    rmdir($directory);
}

test('a shard run names the merge step for a threshold', function (array $arguments, bool $warns): void {
    $output = new BufferedOutput;
    $plugin = new Coverage($output);

    new ReflectionProperty(Coverage::class, 'shardIndex')->setValue($plugin, 1);
    new ReflectionProperty(Coverage::class, 'shardTotal')->setValue($plugin, 2);

    (fn () => $this->parseThresholdOptions($arguments))->call($plugin);
    (fn () => $this->reportShardCoverageStored())->call($plugin);

    $written = $output->fetch();

    expect($written)->toContain('Coverage stored for shard 1/2')
        ->and(str_contains($written, 'runs on the merged total'))->toBe($warns);
})->with([
    'min' => [['--min=90'], true],
    'exactly' => [['--exactly=90.0'], true],
    'neither' => [[], false],
]);

test('find report targets reads each flag', function (array $arguments, array $expected): void {
    $plugin = new Coverage(new NullOutput);

    expect((fn () => $this->findReportTargets($arguments))->call($plugin))->toBe($expected);
})->with([
    'inline value' => [['--coverage-html=build'], ['--coverage-html' => 'build']],
    'separate value' => [['--coverage-html', 'build'], ['--coverage-html' => 'build']],
    'optional value' => [['--coverage-text'], ['--coverage-text' => 'php://stdout']],
    'several flags' => [
        ['--coverage-clover', 'c.xml', '--coverage-jsonl=j'],
        ['--coverage-clover' => 'c.xml', '--coverage-jsonl' => 'j'],
    ],
    'no flag' => [['--min=80'], []],
]);

test('find report targets rejects a flag without a value', function (): void {
    $plugin = new Coverage(new NullOutput);

    expect(fn () => (fn () => $this->findReportTargets(['--coverage-html']))->call($plugin))
        ->toThrow(InvalidOption::class);
});

test('shards coverage writes the reports that the flags ask for', function (): void {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-shards-'.uniqid();
    mkdir($dir, 0755, true);

    file_put_contents($dir.DIRECTORY_SEPARATOR.'Foo.php', "<?php\n\$a = 1;\n\$b = 2;\n");
    file_put_contents(
        $dir.DIRECTORY_SEPARATOR.'1-of-1.cov',
        writeShardCoverage($dir, [2 => [0 => 1], 3 => [0 => 1]]),
    );

    $clover = $dir.DIRECTORY_SEPARATOR.'clover.xml';
    $html = $dir.DIRECTORY_SEPARATOR.'html';

    try {
        $output = new BufferedOutput;
        $plugin = new Coverage($output);

        $result = (fn () => $this->mergeAndReportShardsCoverage(
            $dir,
            ['--coverage-clover', $clover, '--coverage-html', $html],
        ))->call($plugin);

        expect($result)->toBe(100.0)
            ->and($output->fetch())->toContain('Wrote '.$clover)
            ->and(file_exists($clover))->toBeTrue()
            ->and(file_exists($html.DIRECTORY_SEPARATOR.'index.html'))->toBeTrue();
    } finally {
        @unlink($clover);
        @unlink($dir.DIRECTORY_SEPARATOR.'Foo.php');
        @unlink($dir.DIRECTORY_SEPARATOR.'1-of-1.cov');
        deleteDirectory($html);
        @rmdir($dir);
    }
});
