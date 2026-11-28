<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('parallel workers keep the trailing space of an include path as serial phpunit does', function (): void {
    $script = <<<'PHP_WRAP'
    require "vendor/autoload.php";
    $read = fn (array $arguments): string => (new PHPUnit\TextUI\CliArguments\Builder(PHPUnit\Event\Facade::emitter()))->fromParameters(["phpunit", ...$arguments])->includePath();
    $arguments = ["--include-path=lib =extra"];
    $handler = new Pest\Plugins\Parallel\Handlers\IncludePath;
    $forwarded = $handler->handleArguments($arguments);
    echo json_encode(["serial" => $read($arguments), "forwarded" => $read($forwarded), "worker" => $read($handler->handleWorkerArguments([]))]);
    PHP_WRAP;

    $process = new Process(['php', '-r', $script], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(json_decode($process->getOutput(), true))->toBe([
            'serial' => 'lib ',
            'forwarded' => 'lib ',
            'worker' => 'lib ',
        ]);
});
