<?php

declare(strict_types=1);

use ParaTest\WrapperRunner\ApplicationForWrapperWorker;
use ParaTest\WrapperRunner\WrapperWorker;
use Pest\Kernel;
use Pest\Plugins\Actions\CallsHandleArguments;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

$bootPest = (static function (): void {
    $workerArgv = new ArgvInput;

    $rootPath = dirname(PHPUNIT_COMPOSER_INSTALL, 2);
    $testSuite = TestSuite::getInstance($rootPath, $workerArgv->getParameterOption(
        '--test-directory',
        'tests'
    ));

    $input = new ArgvInput;

    $output = new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);

    Kernel::boot($testSuite, $input, $output);
});

(static function () use ($bootPest): void {
    $getopt = getopt('', [
        'status-file:',
        'progress-file:',
        'unexpected-output-file:',
        'test-result-file:',
        'result-cache-file:',
        'teamcity-file:',
        'testdox-file:',
        'testdox-color',
        'testdox-columns:',
        'testdox-summary',
        'phpunit-argv:',
    ]);

    $composerAutoloadFiles = [
        dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'autoload.php',
        dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php',
        dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php',
    ];

    foreach ($composerAutoloadFiles as $file) {

        if (file_exists($file)) {
            require_once $file;
            define('PHPUNIT_COMPOSER_INSTALL', $file);

            break;
        }
    }

    assert(isset($getopt['status-file']) && is_string($getopt['status-file']));
    $statusFile = fopen($getopt['status-file'], 'wb');
    assert(is_resource($statusFile));

    assert(isset($getopt['progress-file']) && is_string($getopt['progress-file']));
    assert(isset($getopt['unexpected-output-file']) && is_string($getopt['unexpected-output-file']));
    assert(isset($getopt['test-result-file']) && is_string($getopt['test-result-file']));
    assert(! isset($getopt['result-cache-file']) || is_string($getopt['result-cache-file']));
    assert(! isset($getopt['teamcity-file']) || is_string($getopt['teamcity-file']));
    assert(! isset($getopt['testdox-file']) || is_string($getopt['testdox-file']));

    assert(isset($getopt['phpunit-argv']) && is_string($getopt['phpunit-argv']));
    $phpunitArgv = unserialize($getopt['phpunit-argv'], ['allowed_classes' => false]);
    assert(is_array($phpunitArgv));

    $bootPest();

    $phpunitArgv = CallsHandleArguments::execute($phpunitArgv);

    $application = new ApplicationForWrapperWorker(
        $phpunitArgv,
        $getopt['progress-file'],
        $getopt['unexpected-output-file'],
        $getopt['test-result-file'],
        $getopt['result-cache-file'] ?? null,
        $getopt['teamcity-file'] ?? null,
        $getopt['testdox-file'] ?? null,
        isset($getopt['testdox-color']),
        (int) ($getopt['testdox-columns'] ?? null),
    );

    while (true) {
        if (feof(STDIN)) {
            $application->end();
            exit;
        }

        $testPath = fgets(STDIN);
        if ($testPath === false || $testPath === WrapperWorker::COMMAND_EXIT) {
            $application->end();
            exit;
        }

        // It must be a 1 byte string to ensure filesize() is equal to the number of tests executed
        $exitCode = $application->runTest(realpath(trim($testPath)));

        fwrite($statusFile, (string) $exitCode);
        fflush($statusFile);
    }
})();
