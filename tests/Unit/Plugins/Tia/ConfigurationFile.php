<?php

declare(strict_types=1);

use Pest\Plugins\Configuration;
use Pest\Plugins\Tia\ConfigurationFile;

beforeEach(function (): void {
    $this->projectRoot = sys_get_temp_dir().'/pest-tia-configuration-'.bin2hex(random_bytes(4));
    mkdir($this->projectRoot, 0755, true);

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
