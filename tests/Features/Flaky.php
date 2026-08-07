<?php

use Symfony\Component\Process\Process;

it('passes on first try', function (): void {
    expect(true)->toBeTrue();
})->flaky();

it('passes on a subsequent try', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_'.crc32(__FILE__.__LINE__);
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        throw new Exception('Flaky failure');
    }

    @unlink($file);
    expect(true)->toBeTrue();
})->flaky(tries: 3);

it('has a default of 3 tries', function (): void {
    expect(true)->toBeTrue();
})->flaky();

it('succeeds on the last possible try', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_last_try';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 3) {
        throw new Exception('Not yet');
    }

    @unlink($file);
    expect(true)->toBeTrue();
})->flaky(tries: 3);

it('works with tries of 1', function (): void {
    expect(true)->toBeTrue();
})->flaky(tries: 1);

it('retries assertion failures', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_assertion';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        expect(false)->toBeTrue();
    }

    @unlink($file);
    expect(true)->toBeTrue();
})->flaky(tries: 3);

it('works with a dataset', function (int $number): void {
    expect($number)->toBeGreaterThan(0);
})->flaky(tries: 2)->with([1, 2, 3]);

it('retries each dataset independently', function (string $label): void {
    $file = sys_get_temp_dir().'/pest_flaky_dataset_'.md5($label);
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        throw new Exception("Flaky for $label");
    }

    @unlink($file);
    expect(true)->toBeTrue();
})->flaky(tries: 3)->with(['alpha', 'beta']);

describe('within a describe block', function (): void {
    it('retries inside describe', function (): void {
        $file = sys_get_temp_dir().'/pest_flaky_describe';
        $count = file_exists($file) ? (int) file_get_contents($file) : 0;
        file_put_contents($file, (string) ++$count);

        if ($count < 2) {
            throw new Exception('Flaky inside describe');
        }

        @unlink($file);
        expect(true)->toBeTrue();
    })->flaky(tries: 2);
});

describe('lifecycle hooks with flaky', function (): void {
    beforeEach(function (): void {
        $this->setupCount = ($this->setupCount ?? 0) + 1;
    });

    it('re-runs beforeEach on each retry', function (): void {
        $file = sys_get_temp_dir().'/pest_flaky_lifecycle';
        $count = file_exists($file) ? (int) file_get_contents($file) : 0;
        file_put_contents($file, (string) ++$count);

        if ($count < 2) {
            throw new Exception('Flaky lifecycle');
        }

        @unlink($file);
        expect($this->setupCount)->toBe(2);
    })->flaky(tries: 3);
});

describe('afterEach with flaky', function (): void {
    $state = new stdClass;
    $state->teardownCount = 0;

    afterEach(function () use ($state): void {
        $state->teardownCount++;
    });

    it('runs afterEach between retries', function () use ($state): void {
        $file = sys_get_temp_dir().'/pest_flaky_aftereach';
        $count = file_exists($file) ? (int) file_get_contents($file) : 0;
        file_put_contents($file, (string) ++$count);

        if ($count < 2) {
            throw new Exception('Flaky afterEach');
        }

        @unlink($file);
        expect($state->teardownCount)->toBe(1);
    })->flaky(tries: 3);
});

it('does not retry skipped tests')
    ->skip('intentionally skipped')
    ->flaky(tries: 3);

it('works with repeat and flaky', function (): void {
    expect(true)->toBeTrue();
})->repeat(times: 2)->flaky(tries: 2);

it('works as higher order test')
    ->assertTrue(true)
    ->flaky(tries: 2);

it('fails after exhausting all retries', function (): void {
    $process = new Process(
        ['php', 'bin/pest', 'tests/Fixtures/Suites/FlakyFailure.php'],
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    expect($process->getExitCode())->not->toBe(0)
        ->and(removeAnsiEscapeSequences($process->getOutput()))->toContain('FAILED')
        ->toContain('Always fails');
});

it('throws when tries is less than 1', function (): void {
    it('invalid', function (): void {})->flaky(tries: 0);
})->throws(InvalidArgumentException::class, 'The number of tries must be greater than 0.');

it('throws when tries is negative', function (): void {
    it('invalid negative', function (): void {})->flaky(tries: -1);
})->throws(InvalidArgumentException::class, 'The number of tries must be greater than 0.');

it('does not retry todo tests')
    ->todo()
    ->flaky(tries: 3);

it('retries php errors', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_error';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        throw new TypeError('type error');
    }

    @unlink($file);
    expect(true)->toBeTrue();
})->flaky(tries: 3);

it('works with throws and flaky', function (): void {
    throw new RuntimeException('Expected exception');
})->throws(RuntimeException::class, 'Expected exception')->flaky(tries: 2);

it('does not retry expected exceptions', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_expected';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count >= 2) {
        @unlink($file);

        return;
    }

    @unlink($file);
    throw new RuntimeException('Expected on first attempt');
})->throws(RuntimeException::class)->flaky(tries: 3);

it('does not retry fails()', function (): void {
    $this->fail('Expected failure');
})->fails('Expected failure')->flaky(tries: 2);

it('retries unexpected exceptions even with throws set', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_unexpected';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        throw new LogicException('Unexpected flaky error');
    }

    @unlink($file);
    throw new RuntimeException('Expected exception');
})->throws(RuntimeException::class)->flaky(tries: 3);

it('does not leak mock objects between retries', function (): void {
    $mock = $this->createMock(Countable::class);
    $mock->expects($this->once())->method('count')->willReturn(1);

    $file = sys_get_temp_dir().'/pest_flaky_mock';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        @unlink(sys_get_temp_dir().'/pest_flaky_mock');
        file_put_contents($file, '1');
        throw new Exception('Flaky mock failure');
    }

    @unlink($file);
    expect($mock->count())->toBe(1);
})->flaky(tries: 3);

it('does not stop retrying when snapshot changes are absent', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_no_snapshot';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        throw new Exception('No snapshots here');
    }

    @unlink($file);
    expect(true)->toBeTrue();
})->flaky(tries: 3);

it('does not leak dynamic properties between retries', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_props';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        $this->leakedProperty = 'from attempt 1';
        throw new Exception('Flaky props');
    }

    @unlink($file);
    expect(property_exists($this, 'leakedProperty') && $this->leakedProperty !== null)->toBeFalse();
})->flaky(tries: 3);

it('clears output buffer between retries when expectOutputString is used', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_output';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    $this->expectOutputString('clean');

    if ($count < 2) {
        echo 'stale';
        throw new Exception('Flaky output');
    }

    @unlink($file);
    echo 'clean';
})->flaky(tries: 3);

it('preserves output between retries when no output expectation is set', function (): void {
    $file = sys_get_temp_dir().'/pest_flaky_output_no_expect';
    $count = file_exists($file) ? (int) file_get_contents($file) : 0;
    file_put_contents($file, (string) ++$count);

    if ($count < 2) {
        echo 'from attempt 1';
        throw new Exception('Flaky output no expect');
    }

    @unlink($file);
    $this->expectOutputString('from attempt 1');
})->flaky(tries: 3);
