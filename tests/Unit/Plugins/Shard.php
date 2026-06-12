<?php

use Pest\Exceptions\InvalidOption;
use Pest\Plugins\Shard;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe('getShard', function () {
    it('parses valid shard format', function (string $format, int $expectedIndex, int $expectedTotal) {
        $input = new ArgvInput(['test', '--shard', $format]);

        $result = Shard::getShard($input);

        expect($result)->toBe([
            'index' => $expectedIndex,
            'total' => $expectedTotal,
        ]);
    })->with([
        ['1/2', 1, 2],
        ['2/2', 2, 2],
        ['1/4', 1, 4],
        ['4/4', 4, 4],
        ['1/10', 1, 10],
        ['10/10', 10, 10],
        ['5/100', 5, 100],
    ]);

    it('throws exception for invalid format', function (array $arguments) {
        $input = new ArgvInput($arguments);

        Shard::getShard($input);
    })->with([
        [['test', '--shard', 'invalid']],
        [['test', '--shard', '1']],
        [['test', '--shard', '1/']],
        [['test', '--shard', '/2']],
        [['test', '--shard', 'a/b']],
        [['test', '--shard', '1.5/2']],
    ])->throws(InvalidOption::class);

    it('throws exception for invalid index or total values', function (array $arguments) {
        $input = new ArgvInput($arguments);

        Shard::getShard($input);
    })->with([
        [['test', '--shard', '0/2']],
        [['test', '--shard', '1/0']],
        [['test', '--shard', '3/2']],
        [['test', '--shard', '5/4']],
    ])->throws(InvalidOption::class);
});

describe('ensureFilterLengthIsSafe', function () {
    it('accepts filter within length limit', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('ensureFilterLengthIsSafe');

        $filter = str_repeat('a', 1000);

        $method->invoke($shard, $filter);

        expect(true)->toBeTrue();
    });

    it('throws exception when filter exceeds default limit', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('ensureFilterLengthIsSafe');

        $filter = str_repeat('a', 32769);

        $method->invoke($shard, $filter);
    })->throws(InvalidOption::class, 'The generated filter for this shard is too long');

    it('respects custom limit from environment variable', function () {
        putenv('PEST_SHARD_MAX_FILTER_LENGTH=1000');

        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('ensureFilterLengthIsSafe');

        $filter = str_repeat('a', 1001);

        try {
            $method->invoke($shard, $filter);
            expect(false)->toBeTrue('Should have thrown exception');
        } catch (InvalidOption $e) {
            expect($e->getMessage())->toContain('1001 characters')
                ->and($e->getMessage())->toContain('limit is 1000 characters');
        } finally {
            putenv('PEST_SHARD_MAX_FILTER_LENGTH');
        }
    });

    it('accepts filter within custom limit', function () {
        putenv('PEST_SHARD_MAX_FILTER_LENGTH=1000');

        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('ensureFilterLengthIsSafe');

        $filter = str_repeat('a', 999);

        try {
            $method->invoke($shard, $filter);
            expect(true)->toBeTrue();
        } catch (InvalidOption) {
            expect(false)->toBeTrue('Should not have thrown exception');
        } finally {
            putenv('PEST_SHARD_MAX_FILTER_LENGTH');
        }
    });
});

describe('handleArguments', function () {
    it('returns original arguments when shard option is not present', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $arguments = ['bin/pest', 'tests/', '--parallel'];

        $result = $shard->handleArguments($arguments);

        expect($result)->toBe($arguments);
    });

    it('removes parallel arguments from test discovery', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('removeParallelArguments');

        $arguments = ['bin/pest', '--parallel', 'tests/', '-p'];

        $result = $method->invoke($shard, $arguments);

        expect($result)->toBe([0 => 'bin/pest', 2 => 'tests/']);
    });
});

describe('addOutput', function () {
    it('displays shard information after test execution', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $property = $reflection->getProperty('shard');
        $property->setValue(null, [
            'index' => 2,
            'total' => 4,
            'testsRan' => 25,
            'testsCount' => 100,
        ]);

        $exitCode = $shard->addOutput(0);
        $outputText = $output->fetch();

        expect($exitCode)->toBe(0)
            ->and($outputText)->toContain('Shard:')
            ->and($outputText)->toContain('2 of 4')
            ->and($outputText)->toContain('25 files ran')
            ->and($outputText)->toContain('out of 100');
    });

    it('uses singular form for single test file', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $property = $reflection->getProperty('shard');
        $property->setValue(null, [
            'index' => 1,
            'total' => 4,
            'testsRan' => 1,
            'testsCount' => 100,
        ]);

        $shard->addOutput(0);
        $outputText = $output->fetch();

        expect($outputText)->toContain('1 file ran')
            ->and($outputText)->not->toContain('1 files');
    });

    it('returns original exit code when shard is not set', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $property = $reflection->getProperty('shard');
        $property->setValue(null, null);

        $exitCode = $shard->addOutput(1);
        $outputText = $output->fetch();

        expect($exitCode)->toBe(1)
            ->and($outputText)->not->toContain('Shard:');
    });
});
