<?php

beforeEach(function () {
    $this->snapshotPath = __DIR__ . '/../.snapshots/teamcity.txt';
    $this->snapshot     = file_get_contents($this->snapshotPath);
});

afterEach(function () {
    if (getenv('REBUILD_SNAPSHOTS')) {
        echo 'Rebuilding snapshots...';
        file_put_contents($this->snapshotPath, $this->output);
    }
});

test('teamcity snapshot test', function () {
    $process = new Symfony\Component\Process\Process(
        ['php', 'bin/pest', '--teamcity', 'tests/Unit/Datasets.php'],
        __DIR__ . '/../..',
        ['EXCLUDE' => 'integration', 'REBUILD_SNAPSHOTS' => false, 'PARATEST' => 0]
    );

    $process->run();

    $this->output = $process->getOutput();
    $this->output = sanitizeOutput($this->output);

    expect($this->output)->toEqual($this->snapshot);
});

function sanitizeOutput(string $output): string
{
    // Sanitize time-specific data as that can vary per run.
    $output = preg_replace("/duration='\d+'/", "duration='10'", $output);
    $output = preg_replace("/(Time:.*?)\d+.\d+s/", '${1}10.10s', $output);
    // Sanitize location hints as that contains full paths
    $output = preg_replace("/(locationHint='pest_qn:\/\/)(.*?)(\/tests\/.*?')/", '${1}${3}', $output);

    return $output;
}
