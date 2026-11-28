<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('parallel keeps the leading space of an include path in the same argument as serial phpunit does', function (): void {
    $script = <<<'PHP_WRAP'
    require "vendor/autoload.php";
    $serial = (new PHPUnit\TextUI\CliArguments\Builder(PHPUnit\Event\Facade::emitter()))->fromParameters(["--include-path= lib"])->includePath();
    $handler = new Pest\Plugins\Parallel\Handlers\IncludePath;
    $forwarded = $handler->handleArguments(["--include-path= lib"]);
    echo json_encode(["serial" => $serial, "forwarded" => $forwarded, "first" => explode(PATH_SEPARATOR, get_include_path())[0], "worker" => $handler->handleWorkerArguments([])]);
    PHP_WRAP;

    $process = new Process(['php', '-r', $script], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(json_decode($process->getOutput(), true))->toBe([
            'serial' => ' lib',
            'forwarded' => ['--include-path= lib'],
            'first' => ' lib',
            'worker' => ['--include-path= lib'],
        ]);
});
