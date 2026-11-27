<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\Project;

test('loads the autoloader of the parent process in a parallel worker', function (): void {
    $project = Project::withoutGit();

    try {
        $checkout = $project->path('checkout');
        $project->scaffoldCheckout($checkout);

        $project->write('vendor/autoload.php', <<<'DECOY'
            <?php

            fwrite(STDERR, 'decoy autoloader');

            exit(1);
            DECOY);

        $project->write('checkout/phpunit.xml', <<<'CONFIGURATION'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit bootstrap="vendor/autoload.php" cacheDirectory=".phpunit.cache"/>
            CONFIGURATION);

        $test = 'checkout/tests/WorkerAutoloadTest.php';
        $project->write($test, <<<'FIXTURE'
            <?php

            test('runs in a worker with the autoloader of the checkout', function (): void {
                expect($_SERVER['PEST_PARALLEL'] ?? null)->toBe('1')
                    ->and(realpath((new ReflectionClass(Pest\TestSuite::class))->getFileName()))->toStartWith(dirname(__DIR__).DIRECTORY_SEPARATOR.'src');
            });
            FIXTURE);

        $process = new Process(
            [PHP_BINARY, $checkout.'/bin/pest', '--configuration='.$project->path('checkout/phpunit.xml'), '--colors=never', $project->path($test), '--parallel', '--processes=2'],
            $checkout,
            ['PARATEST' => '0', 'PAO_DISABLE' => '1'],
        );

        expect($process->run())->toBe(0, $process->getOutput().$process->getErrorOutput())
            ->and($process->getOutput())->toContain('1 passed (2 assertions)');
    } finally {
        Project::destroyAll();
    }
});
