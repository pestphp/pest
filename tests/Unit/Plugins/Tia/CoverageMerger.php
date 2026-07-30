<?php

declare(strict_types=1);

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\Contracts\State;
use Pest\Plugins\Tia\CoverageMerger;
use Pest\Plugins\Tia\FileState;
use Pest\Support\Container;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\ProcessedCodeCoverageData;
use SebastianBergmann\CodeCoverage\Data\ProcessedFunctionCoverageData;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;
use SebastianBergmann\CodeCoverage\Filter;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pest-tia-coverage-merger-'.bin2hex(random_bytes(4));
    mkdir($this->root, 0755, true);

    $this->state = new FileState($this->root);

    Container::getInstance()->add(State::class, $this->state);
});

afterEach(function (): void {
    foreach (glob($this->root.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($this->root);
});

/**
 * @param  array<string, array<int, null|list<string>>>  $lineCoverage
 * @param  array<string, array<string, ProcessedFunctionCoverageData>>  $functionCoverage
 * @param  array<string, array{size: string, status: string}>  $tests
 */
function tiaMergerCoverage(array $lineCoverage, array $functionCoverage, array $tests): CodeCoverage
{
    $coverage = new CodeCoverage(new TiaMergerStubDriver, new Filter);

    $data = new ProcessedCodeCoverageData;
    $data->setLineCoverage($lineCoverage);
    $data->setFunctionCoverage($functionCoverage);

    $coverage->setData($data);
    $coverage->setTests($tests);

    return $coverage;
}

function tiaMergerWriteReport(string $reportPath, CodeCoverage $coverage): void
{
    file_put_contents(
        $reportPath,
        '<?php return unserialize('.var_export(serialize($coverage), true).");\n",
    );
}

describe('applyIfMarked()', function (): void {
    it('drops cached coverage of changed files so stale line numbers do not survive the merge', function (): void {
        $changedFile = '/project/src/Changed.php';
        $stableFile = '/project/src/Stable.php';

        $rerunTest = 'tests/Unit/ChangedTest.php::it covers changed code';
        $untouchedTest = 'tests/Unit/StableTest.php::it covers stable code';

        $tests = [
            $rerunTest => ['size' => 'unknown', 'status' => 'success'],
            $untouchedTest => ['size' => 'unknown', 'status' => 'success'],
        ];

        // The baseline measured Changed.php before its lines shifted.
        $cached = tiaMergerCoverage([
            $changedFile => [10 => [$rerunTest], 12 => [$rerunTest], 14 => null],
            $stableFile => [20 => [$rerunTest, $untouchedTest], 22 => [$untouchedTest]],
        ], [
            $changedFile => ['oldFunction' => new ProcessedFunctionCoverageData([], [])],
            $stableFile => ['stableFunction' => new ProcessedFunctionCoverageData([], [])],
        ], $tests);

        // Changed.php shifted by one line, so the rerun test now covers 11/13.
        $current = tiaMergerCoverage([
            $changedFile => [11 => [$rerunTest], 13 => [$rerunTest], 15 => null],
            $stableFile => [20 => [$rerunTest]],
        ], [
            $changedFile => ['newFunction' => new ProcessedFunctionCoverageData([], [])],
        ], [$rerunTest => $tests[$rerunTest]]);

        $this->state->write(Tia::KEY_COVERAGE_CACHE, gzencode(serialize($cached)));
        $this->state->write(Tia::KEY_COVERAGE_MARKER, '');
        $this->state->write(Tia::KEY_COVERAGE_CHANGED, json_encode([$changedFile]));

        $reportPath = $this->root.'/report.php';
        tiaMergerWriteReport($reportPath, $current);

        CoverageMerger::applyIfMarked($reportPath);

        $merged = require $reportPath;
        assert($merged instanceof CodeCoverage);

        $lineCoverage = $merged->getData()->lineCoverage();

        expect($lineCoverage[$changedFile])->toBe([11 => [$rerunTest], 13 => [$rerunTest], 15 => null])
            ->and($lineCoverage[$stableFile][20])->toBe([$untouchedTest, $rerunTest])
            ->and($lineCoverage[$stableFile][22])->toBe([$untouchedTest]);

        $functionCoverage = $merged->getData()->functionCoverage();

        expect(array_keys($functionCoverage[$changedFile]))->toBe(['newFunction'])
            ->and(array_keys($functionCoverage[$stableFile]))->toBe(['stableFunction'])
            ->and($this->state->exists(Tia::KEY_COVERAGE_CHANGED))->toBeFalse();

        $updatedCache = unserialize(gzdecode($this->state->read(Tia::KEY_COVERAGE_CACHE)));
        assert($updatedCache instanceof CodeCoverage);

        expect($updatedCache->getData()->lineCoverage()[$changedFile])
            ->toBe([11 => [$rerunTest], 13 => [$rerunTest], 15 => null]);
    });

    it('re-attributes rerun tests on unchanged files during the merge', function (): void {
        $file = '/project/src/Example.php';

        $rerunTest = 'tests/Unit/ExampleTest.php::it runs again';
        $untouchedTest = 'tests/Unit/OtherTest.php::it stays cached';

        $cached = tiaMergerCoverage([
            $file => [10 => [$rerunTest, $untouchedTest], 12 => [$rerunTest]],
        ], [], [
            $rerunTest => ['size' => 'unknown', 'status' => 'success'],
            $untouchedTest => ['size' => 'unknown', 'status' => 'success'],
        ]);

        $current = tiaMergerCoverage([
            $file => [10 => [$rerunTest], 12 => []],
        ], [], [
            $rerunTest => ['size' => 'unknown', 'status' => 'success'],
        ]);

        $this->state->write(Tia::KEY_COVERAGE_CACHE, gzencode(serialize($cached)));
        $this->state->write(Tia::KEY_COVERAGE_MARKER, '');

        $reportPath = $this->root.'/report.php';
        tiaMergerWriteReport($reportPath, $current);

        CoverageMerger::applyIfMarked($reportPath);

        $merged = require $reportPath;
        assert($merged instanceof CodeCoverage);

        $lineCoverage = $merged->getData()->lineCoverage();

        expect($lineCoverage[$file][10])->toBe([$untouchedTest, $rerunTest])
            ->and($lineCoverage[$file][12])->toBe([]);
    });

    it('stores the current run as the baseline and clears the changed list when no cache exists', function (): void {
        $file = '/project/src/Example.php';
        $test = 'tests/Unit/ExampleTest.php::it runs';

        $current = tiaMergerCoverage([
            $file => [10 => [$test]],
        ], [], [
            $test => ['size' => 'unknown', 'status' => 'success'],
        ]);

        $this->state->write(Tia::KEY_COVERAGE_MARKER, '');
        $this->state->write(Tia::KEY_COVERAGE_CHANGED, json_encode([$file]));

        $reportPath = $this->root.'/report.php';
        tiaMergerWriteReport($reportPath, $current);

        CoverageMerger::applyIfMarked($reportPath);

        expect($this->state->exists(Tia::KEY_COVERAGE_CHANGED))->toBeFalse();

        $cache = unserialize(gzdecode($this->state->read(Tia::KEY_COVERAGE_CACHE)));
        assert($cache instanceof CodeCoverage);

        expect($cache->getData()->lineCoverage()[$file])->toBe([10 => [$test]]);
    });
});

final class TiaMergerStubDriver extends Driver
{
    public function name(): string
    {
        return 'stub';
    }

    public function version(): string
    {
        return '0.0.0';
    }

    public function start(): void
    {
        // no-op
    }

    public function stop(): RawCodeCoverageData
    {
        return RawCodeCoverageData::fromXdebugWithoutPathCoverage([]);
    }
}
