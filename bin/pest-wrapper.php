<?php

declare(strict_types=1);

use ParaTest\WrapperRunner\ApplicationForWrapperWorker;
use ParaTest\WrapperRunner\WrapperWorker;
use Pest\ConfigLoader;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArgvInput;

(static function (): void {
    $getopt = getopt('', [
        'status-file:',
        'progress-file:',
        'testresult-file:',
        'teamcity-file:',
        'testdox-file:',
        'testdox-color',
        'phpunit-argv:',
    ]);

    require_once __DIR__ . '/../overrides/Runner/TestSuiteLoader.php';
    require_once __DIR__ . '/../overrides/Runner/Filter/NameFilterIterator.php';

    $composerAutoloadFiles = [
        dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
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
    assert(isset($getopt['testresult-file']) && is_string($getopt['testresult-file']));
    assert(!isset($getopt['teamcity-file']) || is_string($getopt['teamcity-file']));
    assert(!isset($getopt['testdox-file']) || is_string($getopt['testdox-file']));

    assert(isset($getopt['phpunit-argv']) && is_string($getopt['phpunit-argv']));
    $phpunitArgv = unserialize($getopt['phpunit-argv'], ['allowed_classes' => false]);
    assert(is_array($phpunitArgv));

    /**
     * We need to instantiate the Pest Test suite instance
     * so that Pest is able to execute correctly.
     */
    $argv = new ArgvInput();
    $rootPath = dirname(PHPUNIT_COMPOSER_INSTALL, 2);
    TestSuite::getInstance(
        $rootPath,
        $argv->getParameterOption('--test-directory', (new ConfigLoader($rootPath))->getTestsDirectory()),
    );

    $application = new ApplicationForWrapperWorker(
        $phpunitArgv,
        $getopt['progress-file'],
        $getopt['testresult-file'],
        $getopt['teamcity-file'] ?? null,
        $getopt['testdox-file'] ?? null,
        isset($getopt['testdox-color']),
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
        $exitCode = $application->runTest(trim($testPath));

        fwrite($statusFile, (string) $exitCode);
        fflush($statusFile);
    }
})();
