<?php

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\FileState;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Storage;
use Pest\Support\Str;
use Symfony\Component\Process\Process;

test('replaying cached skipped and incomplete results does not run user hooks', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $home = sys_get_temp_dir().'/pest-tia-replay-'.bin2hex(random_bytes(8));
    $log = $home.'/hooks.log';

    mkdir($home, 0755, true);

    try {
        $changedFiles = new ChangedFiles($projectRoot);
        $branch = $changedFiles->currentBranch() ?? 'main';
        $sha = $changedFiles->currentSha();

        expect($sha)->not->toBeNull();

        $fixture = 'tests/.tests/TiaReplayHooks.php';
        $classFQN = 'P\Tests\tests\TiaReplayHooks';

        $graph = new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot));
        $graph->setRecordedAtSha($branch, $sha);
        $graph->setLastRunTree($branch, $changedFiles->snapshotTree($changedFiles->since($sha) ?? []));
        $graph->markKnownTestFiles([$fixture]);
        $graph->setResult($branch, $classFQN.'::'.Str::evaluable('replayed pass'), 0, '', 0.001, 1, $fixture);
        $graph->setResult($branch, $classFQN.'::'.Str::evaluable('replayed skip'), 1, 'cached skip', 0.001, 0, $fixture);
        $graph->setResult($branch, $classFQN.'::'.Str::evaluable('replayed incomplete'), 2, 'cached incomplete', 0.001, 0, $fixture);

        $originalHome = getenv('HOME');
        putenv('HOME='.$home);

        try {
            $storageDir = Storage::tempDir($projectRoot);
        } finally {
            putenv($originalHome === false ? 'HOME' : 'HOME='.$originalHome);
        }

        $json = $graph->encode();

        expect($json)->not->toBeNull()
            ->and(new FileState($storageDir)->write(Tia::KEY_GRAPH, (string) $json))->toBeTrue();

        $process = new Process(
            ['php', 'bin/pest', $fixture, '--tia'],
            $projectRoot,
            [
                'COLLISION_PRINTER' => 'DefaultPrinter',
                'COLLISION_IGNORE_DURATION' => 'true',
                'PARATEST' => 0,
                'PAO_DISABLE' => '1',
                'HOME' => $home,
                'TIA_REPLAY_HOOKS_LOG' => $log,
            ],
        );

        $process->run();

        $output = removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput());

        expect($output)
            ->toContain('3 replayed')
            ->toContain('1 incomplete')
            ->toContain('1 skipped')
            ->toContain('1 passed')
            ->not->toContain('must not run for replayed tests')
            ->and($process->getExitCode())->toBe(0)
            ->and(file_exists($log))->toBeFalse();
    } finally {
        $delete = function (string $path) use (&$delete): void {
            foreach (glob($path.'/{,.}*', GLOB_BRACE | GLOB_NOSORT) ?: [] as $entry) {
                if (in_array(basename($entry), ['.', '..'], true)) {
                    continue;
                }

                is_dir($entry) && ! is_link($entry) ? $delete($entry) : @unlink($entry);
            }

            @rmdir($path);
        };

        $delete($home);
    }
})->skipOnWindows();
