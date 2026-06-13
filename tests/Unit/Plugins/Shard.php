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

describe('buildFilterArgument', function () {
    it('generates compact filter for single test', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildFilterArgument');

        $filter = $method->invoke($shard, ['Tests\\Unit\\ExampleTest']);

        expect($filter)->toBe('Tests\\\\Unit\\\\ExampleTest');
    });

    it('generates compact filter for multiple tests with common prefix', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildFilterArgument');

        $filter = $method->invoke($shard, [
            'Tests\\Unit\\Foo\\BarTest',
            'Tests\\Unit\\Foo\\BazTest',
        ]);

        expect($filter)->toBe('Tests\\\\Unit\\\\Foo\\\\(BarTest|BazTest)');
    });

    it('generates compact filter for tests with different namespaces', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildFilterArgument');

        $filter = $method->invoke($shard, [
            'Tests\\Unit\\FooTest',
            'Tests\\Feature\\BarTest',
        ]);

        expect($filter)->toBe('Tests\\\\(Unit\\\\FooTest|Feature\\\\BarTest)');
    });

    it('returns empty string for empty test list', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildFilterArgument');

        $filter = $method->invoke($shard, []);

        expect($filter)->toBe('');
    });

    it('generates compact filter for deeply nested namespaces', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildFilterArgument');

        $filter = $method->invoke($shard, [
            'Tests\\Unit\\Plugins\\Concerns\\Foo',
            'Tests\\Unit\\Plugins\\Concerns\\Bar',
            'Tests\\Unit\\Plugins\\Concerns\\Baz',
        ]);

        expect($filter)->toBe('Tests\\\\Unit\\\\Plugins\\\\Concerns\\\\(Foo|Bar|Baz)');
    });

    it('handles mix of nested and flat namespaces', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildFilterArgument');

        $tests = [
            'Tests\\Unit\\SimpleTest',
            'Tests\\Unit\\Plugins\\Concerns\\HandleArguments',
            'Tests\\Unit\\Plugins\\Concerns\\Validation',
            'Tests\\Unit\\Another\\Deep\\Nested\\Test',
        ];

        $filter = $method->invoke($shard, $tests);

        expect($filter)
            ->toBe(addslashes('Tests\\Unit\\(SimpleTest|Plugins\\Concerns\\(HandleArguments|Validation)|Another\\Deep\\Nested\\Test)'));
    });
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

        $arguments = ['bin/pest', '--parallel', '--processes=4', 'tests/', '-p'];

        $result = $method->invoke($shard, $arguments);

        expect($result)->toBe(['bin/pest', 'tests/']);
    });
});

describe('parseListTestsOutput', function () {
    it('parses Tests\\ namespaced classes from --list-tests output', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('parseListTestsOutput');

        $listOutput = <<<'OUT'
 INFO  Available tests:

 - P\Tests\Features\After::__pest_evaluable_it_runs
 - P\Tests\Features\After::__pest_evaluable_it_runs_twice
 - P\Tests\Unit\Foo::test_bar
OUT;

        expect($method->invoke($shard, $listOutput))->toBe([
            'Tests\\Features\\After',
            'Tests\\Unit\\Foo',
        ]);
    });

    it('deduplicates repeated class names from multiple test methods', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('parseListTestsOutput');

        $listOutput = <<<'OUT'
 - P\Tests\Same::method_a
 - P\Tests\Same::method_b
 - P\Tests\Same::method_c
OUT;

        expect($method->invoke($shard, $listOutput))->toBe(['Tests\\Same']);
    });

    it('returns an empty list for output with no matching lines', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('parseListTestsOutput');

        expect($method->invoke($shard, ''))->toBe([])
            ->and($method->invoke($shard, 'some random text'))->toBe([]);
    });

    it('parses non-Tests namespaced classes', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('parseListTestsOutput');

        $listOutput = <<<'OUT'
 - P\Acme\Sharding\OneTest::test_foo
 - P\Acme\Sharding\TwoTest::test_bar
 - App\Suite\BazTest::test_qux
OUT;

        expect($method->invoke($shard, $listOutput))->toBe([
            'Acme\\Sharding\\OneTest',
            'Acme\\Sharding\\TwoTest',
            'App\\Suite\\BazTest',
        ]);
    });

    it('parses unnamespaced top-level classes', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('parseListTestsOutput');

        expect($method->invoke($shard, ' - P\FooTest::test_bar'))->toBe(['FooTest']);
    });

    it('strips the P\\ Pest prefix but keeps the rest of the FQCN', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('parseListTestsOutput');

        $listOutput = <<<'OUT'
 - P\Acme\OneTest::a
 - Acme\TwoTest::b
OUT;

        expect($method->invoke($shard, $listOutput))->toBe([
            'Acme\\OneTest',
            'Acme\\TwoTest',
        ]);
    });

    it('ignores junk lines that lack the " - …::" framing', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('parseListTestsOutput');

        $listOutput = <<<'OUT'
 INFO  Available tests:

There were errors:
garbage ::: not a test
 - P\Acme\RealTest::method
OUT;

        expect($method->invoke($shard, $listOutput))->toBe(['Acme\\RealTest']);
    });
});

describe('buildListTestsCommand', function () {
    it('builds the list-tests command with the forwarded --test-directory', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildListTestsCommand');

        $command = $method->invoke($shard, ['bin/pest', '--update-shards'], 'custom/suite');

        expect($command)->toBe([
            'php',
            'bin/pest',
            '--update-shards',
            '--test-directory=custom/suite',
            '--list-tests',
        ]);
    });

    it('strips --parallel and -p when building the list-tests command', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildListTestsCommand');

        $command = $method->invoke($shard, ['bin/pest', '--parallel', '--update-shards', '-p'], 'tests');

        expect($command)->toBe([
            'php',
            'bin/pest',
            '--update-shards',
            '--test-directory=tests',
            '--list-tests',
        ]);
    });

    it('forwards --test-directory even when input arguments include one', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildListTestsCommand');

        $command = $method->invoke($shard, ['bin/pest'], 'suites');

        expect($command)->toContain('--test-directory=suites');
    });

    it('strips --processes=N when building the list-tests command', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildListTestsCommand');

        $command = $method->invoke($shard, ['bin/pest', '--parallel', '--processes=4', '--update-shards'], 'tests');

        expect($command)->toBe([
            'php',
            'bin/pest',
            '--update-shards',
            '--test-directory=tests',
            '--list-tests',
        ]);
    });

    it('strips --processes N (space-separated) when building the list-tests command', function () {
        $output = new BufferedOutput;
        $shard = new Shard($output);

        $reflection = new ReflectionClass($shard);
        $method = $reflection->getMethod('buildListTestsCommand');

        $command = $method->invoke($shard, ['bin/pest', '--parallel', '--processes', '4', '--update-shards'], 'tests');

        expect($command)->not->toContain('--processes')
            ->and($command)->toContain('--update-shards')
            ->and($command)->toContain('--test-directory=tests');
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
