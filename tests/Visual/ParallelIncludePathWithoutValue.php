<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('parallel keeps the pest runner when the include path option has no value', function (): void {
    $script = <<<'PHP_WRAP'
    require "vendor/autoload.php";
    $handlers = array_values(array_intersect(
        (new ReflectionClassConstant(Pest\Plugins\Parallel::class, "HANDLERS"))->getValue(),
        [Pest\Plugins\Parallel\Handlers\Parallel::class, Pest\Plugins\Parallel\Handlers\IncludePath::class],
    ));
    $forwarded = array_reduce($handlers, fn (array $arguments, string $handler): array => (new $handler)->handleArguments($arguments), ["--parallel", "--include-path="]);
    echo json_encode(["runner" => in_array("--runner=".Pest\Plugins\Parallel\Paratest\WrapperRunner::class, $forwarded, true)]);
    PHP_WRAP;

    $process = new Process(['php', '-r', $script], dirname(__DIR__, 2));

    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and(json_decode($process->getOutput(), true))->toBe(['runner' => true]);
});
