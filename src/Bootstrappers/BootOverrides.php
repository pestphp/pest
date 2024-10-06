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
     * The list of files to be overridden.
     *
     * @var array<string, string>
     */
    public const FILES = [
        '53c246e5f416a39817ac81124cdd64ea8403038d01d7a202e1ffa486fbdf3fa7' => 'Runner/Filter/NameFilterIterator.php',
        'a4a43de01f641c6944ee83d963795a46d32b5206b5ab3bbc6cce76e67190acbf' => 'Runner/ResultCache/DefaultResultCache.php',
        'd0e81317889ad88c707db4b08a94cadee4c9010d05ff0a759f04e71af5efed89' => 'Runner/TestSuiteLoader.php',
        '3bb609b0d3bf6dee8df8d6cd62a3c8ece823c4bb941eaaae39e3cb267171b9d2' => 'TextUI/Command/Commands/WarmCodeCoverageCacheCommand.php',
        '8abdad6413329c6fe0d7d44a8b9926e390af32c0b3123f3720bb9c5bbc6fbb7e' => 'TextUI/Output/Default/ProgressPrinter/Subscriber/TestSkippedSubscriber.php',
        'b4250fc3ffad5954624cb5e682fd940b874e8d3422fa1ee298bd7225e1aa5fc2' => 'TextUI/TestSuiteFilterProcessor.php',
        '357d5cd7007f8559b26e1b8cdf43bb6fb15b51b79db981779da6f31b7ec39dad' => 'Event/Value/ThrowableBuilder.php',
        '676273f1fe483877cf2d95c5aedbf9ae5d6a8e2f4c12d6ce716df6591e6db023' => 'Logging/JUnit/JunitXmlLogger.php',
    ];

    /**
     * Boots the list of files to be overridden.
     */
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
