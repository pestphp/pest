<?php

declare(strict_types=1);

use Pest\Restarters\PcovRestarter;
use Symfony\Component\Process\Process;

it('preserves the active memory limit in the restarted process', function (): void {
    $originalMemoryLimit = ini_get('memory_limit');

    try {
        ini_set('memory_limit', '1234M');

        $command = new ReflectionMethod(PcovRestarter::class, 'command')
            ->invoke(new PcovRestarter, __DIR__, ['-r', 'fwrite(STDOUT, (string) ini_get("memory_limit"));']);

        assert(is_array($command));

        $process = new Process($command);
        $process->mustRun();

        expect($process->getOutput())->toBe('1234M');
    } finally {
        ini_set('memory_limit', (string) $originalMemoryLimit);
    }
});

it('preserves pcov.enabled in the restarted process', function (): void {
    $command = new ReflectionMethod(PcovRestarter::class, 'command')
        ->invoke(new PcovRestarter, __DIR__, []);

    assert(is_array($command));

    $index = array_search('pcov.enabled='.ini_get('pcov.enabled'), $command, true);

    expect($index)->toBeInt()
        ->and($command[$index - 1])->toBe('-d');
});
