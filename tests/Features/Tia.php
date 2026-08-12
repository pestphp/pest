<?php

use Pest\Plugins\Tia;
use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\FileState;
use Pest\Plugins\Tia\Fingerprint;
use Pest\Plugins\Tia\Graph;
use Pest\Plugins\Tia\Storage;
use Pest\Support\Str;
use Symfony\Component\Process\Process;

it('does not run user hooks when replaying cached skipped and incomplete results', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $home = sys_get_temp_dir().'/pest-tia-'.bin2hex(random_bytes(8));
    $fixture = 'tests/Fixtures/Suites/TiaReplayHooks.php';
    $arguments = ['--configuration', 'tests/Fixtures/Suites/TiaReplayHooks.xml', '--tia'];

    mkdir($home, 0755, true);

    try {
        $changedFiles = new ChangedFiles($projectRoot);
        $branch = $changedFiles->currentBranch() ?? 'main';
        $sha = $changedFiles->currentSha();

        $id = fn (string $description): string => 'P\Tests\Fixtures\Suites\TiaReplayHooks::'.Str::evaluable($description);

        $graph = new Graph($projectRoot);
        $graph->setFingerprint(Fingerprint::compute($projectRoot, $arguments));
        $graph->setRecordedAtSha($branch, $sha);
        $graph->setLastRunTree($branch, $changedFiles->snapshotTree($changedFiles->since($sha) ?? []));
        $graph->markKnownTestFiles([$fixture]);
        $graph->setResult($branch, $id('replayed pass'), 0, '', 0.01, 1, $fixture);
        $graph->setResult($branch, $id('replayed skip'), 1, 'cached skip', 0.01, 0, $fixture);
        $graph->setResult($branch, $id('replayed incomplete'), 2, 'cached incomplete', 0.01, 0, $fixture);

        $json = $graph->encode();

        expect($json)->not->toBeNull();

        $originalHome = getenv('HOME');
        putenv('HOME='.$home);

        try {
            $storage = new FileState(Storage::tempDir($projectRoot));
        } finally {
            putenv($originalHome === false ? 'HOME' : 'HOME='.$originalHome);
        }

        expect($storage->write(Tia::KEY_GRAPH, (string) $json))->toBeTrue();

        $process = new Process(
            ['php', 'bin/pest', ...$arguments],
            $projectRoot,
            [
                'COLLISION_PRINTER' => 'DefaultPrinter',
                'COLLISION_IGNORE_DURATION' => 'true',
                'PARATEST' => 0,
                'PAO_DISABLE' => '1',
                'HOME' => $home,
            ],
        );

        $process->run();

        $output = removeAnsiEscapeSequences($process->getOutput().$process->getErrorOutput());

        expect($output)->toContain('3 replayed')
            ->and($output)->not->toContain('must not run for replayed tests')
            ->and($output)->toContain('1 incomplete, 1 skipped, 1 passed')
            ->and($process->getExitCode())->toBe(0);
    } finally {
        $paths = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($home, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($paths as $path) {
            $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
        }

        @rmdir($home);
    }
})->skipOnWindows();
