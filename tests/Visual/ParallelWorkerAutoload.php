<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('loads the autoloader of the parent process in a parallel worker', function (): void {
    $autoloader = tempnam(sys_get_temp_dir(), 'pest-autoload');

    try {
        file_put_contents($autoloader, '<?php echo "autoloader of the parent process"; exit(0);');

        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/bin/worker.php', '--composer-autoload='.$autoloader]);

        expect($process->run())->toBe(0)
            ->and($process->getOutput())->toBe('autoloader of the parent process');
    } finally {
        unlink($autoloader);
    }
});
