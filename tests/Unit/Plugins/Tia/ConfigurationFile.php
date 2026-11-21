<?php

declare(strict_types=1);

use Pest\Plugins\Configuration;
use Pest\Plugins\Tia\ConfigurationFile;

beforeEach(function (): void {
    $this->projectRoot = sys_get_temp_dir().'/pest-tia-configuration-'.bin2hex(random_bytes(4));
    mkdir($this->projectRoot.'/lib', 0755, true);

    $contents = "<?xml version=\"1.0\"?>\n<phpunit/>\n";
    file_put_contents($this->projectRoot.'/phpunit.xml', $contents);
    file_put_contents($this->projectRoot.'/phpunit.copy.xml', $contents);
});

afterEach(function (): void {
    foreach (['phpunit.xml', 'phpunit.copy.xml'] as $file) {
        if (is_file($this->projectRoot.'/'.$file)) {
            unlink($this->projectRoot.'/'.$file);
        }
    }

    @rmdir($this->projectRoot.'/lib');
    @rmdir($this->projectRoot);
});

it('fingerprints the path and the content of the configuration file', function (): void {
    $fingerprint = ConfigurationFile::at($this->projectRoot.'/phpunit.xml')->fingerprint($this->projectRoot);

    expect($fingerprint)->toBe('phpunit.xml:'.hash_file('xxh128', $this->projectRoot.'/phpunit.xml'));
});

it('tells an identical copy under another name apart from the original', function (): void {
    expect(ConfigurationFile::at($this->projectRoot.'/phpunit.copy.xml')->fingerprint($this->projectRoot))
        ->not->toBe(ConfigurationFile::at($this->projectRoot.'/phpunit.xml')->fingerprint($this->projectRoot));
});

it('resolves a configuration directory the way PHPUnit does', function (): void {
    expect(ConfigurationFile::fromArguments(['--configuration', $this->projectRoot])->fingerprint($this->projectRoot))
        ->toBe(ConfigurationFile::projectDefault()->fingerprint($this->projectRoot));
});

it('gives a run with --no-configuration its own fingerprint', function (): void {
    expect(ConfigurationFile::fromArguments(['--no-configuration'])->fingerprint($this->projectRoot))
        ->toBe('none')
        ->not->toBe(ConfigurationFile::projectDefault()->fingerprint($this->projectRoot));
});

it('fingerprints the configuration Pest generates the way the Xdebug restarter does', function (array $leftovers): void {
    unlink($this->projectRoot.'/phpunit.xml');

    foreach ($leftovers as $leftover) {
        file_put_contents($this->projectRoot.'/'.$leftover, '');
    }

    $workingDirectory = (string) getcwd();
    chdir($this->projectRoot);
    $configuration = new Configuration;

    try {
        $arguments = $configuration->handleArguments(['pest', '--tia']);
        $generatedPath = $configuration->generatedConfigurationPath();

        expect($generatedPath)->toBeString()
            ->and(basename((string) $generatedPath))->not->toBeIn($leftovers)
            ->and(ConfigurationFile::fromArguments($arguments, $generatedPath)->fingerprint($this->projectRoot))
            ->toBeNull()
            ->toBe(ConfigurationFile::fromArguments(['pest', '--tia'])->fingerprint($this->projectRoot));
    } finally {
        $configuration->terminate();
        chdir($workingDirectory);

        foreach ($leftovers as $leftover) {
            unlink($this->projectRoot.'/'.$leftover);
        }
    }
})->with([
    'first name' => [[]],
    'fallback name' => [['.pest.xml']],
    'random name' => [['.pest.xml', '.pest-fallback.xml']],
]);

it('fingerprints an explicit configuration apart from the one Pest generates', function (): void {
    $generatedPath = $this->projectRoot.'/.pest.xml';
    file_put_contents($generatedPath, '');

    try {
        expect(ConfigurationFile::fromArguments(['-c'.$this->projectRoot.'/phpunit.copy.xml'], $generatedPath)->fingerprint($this->projectRoot))
            ->toBe(ConfigurationFile::at($this->projectRoot.'/phpunit.copy.xml')->fingerprint($this->projectRoot))
            ->not->toBe(ConfigurationFile::fromArguments(['--configuration', $generatedPath], $generatedPath)->fingerprint($this->projectRoot));
    } finally {
        unlink($generatedPath);
    }
});

it('keeps the fingerprint of a run that sets no bootstrap and no include path', function (): void {
    expect(ConfigurationFile::fromArguments(['--tia', '--parallel', '--configuration', $this->projectRoot.'/phpunit.xml'])->fingerprint($this->projectRoot))
        ->toBe(ConfigurationFile::at($this->projectRoot.'/phpunit.xml')->fingerprint($this->projectRoot));
});

it('fingerprints the bootstrap the command line sets relative to the project', function (array $bootstrap): void {
    $arguments = ['--tia', '--configuration', $this->projectRoot.'/phpunit.xml', ...$bootstrap];

    expect(ConfigurationFile::fromArguments($arguments)->fingerprint($this->projectRoot))
        ->toBe(ConfigurationFile::at($this->projectRoot.'/phpunit.xml')->fingerprint($this->projectRoot).';bootstrap=phpunit.copy.xml');
})->with([
    'separate value' => fn (): array => ['--bootstrap', $this->projectRoot.'/phpunit.copy.xml'],
    'equals form' => fn (): array => ['--bootstrap='.$this->projectRoot.'/phpunit.copy.xml'],
]);

it('tells two bootstraps apart', function (): void {
    $configuration = ['--configuration', $this->projectRoot.'/phpunit.xml'];

    expect(ConfigurationFile::fromArguments([...$configuration, '--bootstrap', 'one.php'])->fingerprint($this->projectRoot))
        ->not->toBe(ConfigurationFile::fromArguments([...$configuration, '--bootstrap', 'two.php'])->fingerprint($this->projectRoot));
});

it('fingerprints each include path the command line sets relative to the project', function (): void {
    $includePath = $this->projectRoot.'/lib'.PATH_SEPARATOR.'/elsewhere';

    expect(ConfigurationFile::fromArguments(['--configuration', $this->projectRoot.'/phpunit.xml', '--include-path', $includePath])->fingerprint($this->projectRoot))
        ->toBe(ConfigurationFile::at($this->projectRoot.'/phpunit.xml')->fingerprint($this->projectRoot).';include-path=lib'.PATH_SEPARATOR.'/elsewhere');
});

it('fingerprints the bootstrap of a run with --no-configuration', function (): void {
    expect(ConfigurationFile::fromArguments(['--no-configuration', '--bootstrap', 'boot.php'])->fingerprint($this->projectRoot))
        ->toBe('none;bootstrap=boot.php');
});

it('fingerprints an include path equal to the project root as the project root', function (): void {
    $includePath = $this->projectRoot.PATH_SEPARATOR.$this->projectRoot.'/lib';

    expect(ConfigurationFile::fromArguments(['--configuration', $this->projectRoot.'/phpunit.xml', '--include-path', $includePath])->fingerprint($this->projectRoot))
        ->toEndWith(';include-path=.'.PATH_SEPARATOR.'lib');
});

it('keeps an empty include path entry empty', function (): void {
    expect(ConfigurationFile::fromArguments(['--configuration', $this->projectRoot.'/phpunit.xml', '--include-path', PATH_SEPARATOR.'lib'])->fingerprint($this->projectRoot))
        ->toEndWith(';include-path='.PATH_SEPARATOR.'lib');
});

it('keeps the fingerprint of a relative bootstrap when the working directory changes during the run', function (): void {
    $workingDirectory = (string) getcwd();
    chdir($this->projectRoot);

    try {
        $configuration = ConfigurationFile::fromArguments(['--configuration', 'phpunit.xml', '--bootstrap', './phpunit.copy.xml', '--include-path', './lib']);
        $start = $configuration->fingerprint($this->projectRoot);

        chdir($this->projectRoot.'/lib');

        expect($configuration->fingerprint($this->projectRoot))
            ->toBe($start)
            ->toEndWith(';bootstrap=phpunit.copy.xml;include-path=lib');
    } finally {
        chdir($workingDirectory);
    }
});
