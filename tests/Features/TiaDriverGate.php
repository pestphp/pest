<?php

use Symfony\Component\Process\Process;

test('tia is skipped when pcov is loaded but disabled', function (): void {
    $home = sys_get_temp_dir().'/pest_tia_gate_'.uniqid();
    mkdir($home, 0777, true);

    $iniDir = $home.'/ini';
    mkdir($iniDir, 0777, true);
    file_put_contents($iniDir.'/zz-pcov-disabled.ini', "pcov.enabled=0\n");

    $process = new Process([
        'php', 'bin/pest', 'tests/Fixtures/DirectoryWithTests/ExampleTest.php', '--tia',
    ], dirname(__DIR__, 2), [
        'COLLISION_PRINTER' => 'DefaultPrinter',
        'COLLISION_IGNORE_DURATION' => 'true',
        'PAO_DISABLE' => '1',
        'HOME' => $home,
        'PHP_INI_SCAN_DIR' => ':'.$iniDir,
    ]);

    $process->run();

    $output = (string) removeAnsiEscapeSequences($process->getOutput());

    expect($process->getExitCode())->toBe(0)
        ->and($output)->toContain('ext-pcov or Xdebug')
        ->and(glob($home.'/.pest/tia/*/graph.json'))->toBeEmpty();
})->skipOnWindows()->skip(! extension_loaded('pcov'), 'requires ext-pcov');

test('tia records a graph when pcov is loaded and enabled', function (): void {
    $home = sys_get_temp_dir().'/pest_tia_gate_'.uniqid();
    mkdir($home, 0777, true);

    $iniDir = $home.'/ini';
    mkdir($iniDir, 0777, true);
    file_put_contents($iniDir.'/zz-pcov-enabled.ini', "pcov.enabled=1\n");

    $process = new Process([
        'php', 'bin/pest', 'tests/Fixtures/DirectoryWithTests/ExampleTest.php', '--tia',
    ], dirname(__DIR__, 2), [
        'COLLISION_PRINTER' => 'DefaultPrinter',
        'COLLISION_IGNORE_DURATION' => 'true',
        'PAO_DISABLE' => '1',
        'HOME' => $home,
        'PHP_INI_SCAN_DIR' => ':'.$iniDir,
    ]);

    $process->run();

    $output = (string) removeAnsiEscapeSequences($process->getOutput());

    expect($process->getExitCode())->toBe(0)
        ->and($output)->not->toContain('ext-pcov or Xdebug')
        ->and(glob($home.'/.pest/tia/*/graph.json'))->not->toBeEmpty();
})->skipOnWindows()->skip(! extension_loaded('pcov'), 'requires ext-pcov');
