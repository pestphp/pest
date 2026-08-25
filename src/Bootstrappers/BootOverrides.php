<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Pest\Contracts\Bootstrapper;
use Pest\Exceptions\ShouldNotHappen;

/**
 * @internal
 */
final class BootOverrides implements Bootstrapper
{
    /**
     * @var array<int, string>
     */
    public const array FILES = [
        'ParaTest/WrapperRunner/ProgressPrinterOutput.php',
        'Runner/Filter/NameFilterIterator.php',
        'Runner/TestRunHistory/DefaultTestRunHistory.php',
        'Runner/TestSuiteLoader.php',
        'Runner/TestSuiteSorter.php',
        'TextUI/Command/Commands/WarmCodeCoverageCacheCommand.php',
        'TextUI/Output/Default/ProgressPrinter/Subscriber/TestSkippedSubscriber.php',
        'TextUI/TestSuiteFilterProcessor.php',
        'Event/Value/ThrowableBuilder.php',
        'Logging/JUnit/JunitXmlLogger.php',
    ];

    public function boot(): void
    {
        foreach (self::FILES as $file) {
            $file = __DIR__."/../../overrides/$file";

            if (! file_exists($file)) {
                throw ShouldNotHappen::fromMessage(sprintf('File [%s] does not exist.', $file));
            }

            require_once $file;
        }
    }
}
