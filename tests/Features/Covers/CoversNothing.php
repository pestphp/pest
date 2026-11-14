<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Process\Process;

$run = function (string ...$arguments): array {
    $process = new Process(
        ['php', 'bin/pest', '--configuration', 'tests/Fixtures/Suites/CoversNothing.xml', ...$arguments],
        dirname(__DIR__, 3),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    return [
        'output' => removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput()),
        'code' => $process->getExitCode(),
    ];
};

it('uses the correct PHPUnit attribute for covers nothing', function (): void {
    $attributes = new ReflectionMethod($this, $this->name())->getAttributes();

    expect($attributes[2]->getName())->toBe(CoversNothing::class)
        ->and($attributes[2]->getArguments())->toBeEmpty();
})->coversNothing();

describe('describe', function (): void {
    it('uses the correct PHPUnit attribute for covers nothing', function (): void {
        $attributes = new ReflectionMethod($this, $this->name())->getAttributes();

        expect($attributes[2]->getName())->toBe(CoversNothing::class)
            ->and($attributes[2]->getArguments())->toBeEmpty();
    });
})->coversNothing();

it('is not risky when coverage metadata is required', function () use ($run): void {
    $result = $run();

    expect($result['output'])
        ->toContain('✓ covers nothing')
        ->toContain('! declares no coverage metadata → This test does not define a code coverage target but is expected to do so')
        ->toContain('Tests:    1 risky, 1 passed (2 assertions)')
        ->and($result['code'])->toBe(1);
})->skipOnWindows();

it('does not conflict with covers in the same file', function () use ($run): void {
    $result = $run('tests/Fixtures/Suites/CoversNothingWithCovers.php');

    expect($result['output'])
        ->toContain('✓ covers nothing')
        ->toContain('Tests:    2 passed (2 assertions)')
        ->and($result['code'])->toBe(0);
})->skipOnWindows();
