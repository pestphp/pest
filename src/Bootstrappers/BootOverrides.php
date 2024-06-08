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
        '4f57b79c6ca77cab241cef879ea98bc743d2cd1fbe4586ab652608bf29aa4176' => 'Runner/Filter/NameFilterIterator.php',
        '288a312ae73fa1cea0325e5862f76e5641f1f3f132cf6e3d5df4811c57571c41' => 'Runner/ResultCache/DefaultResultCache.php',
        'a3daa830857b9fb8fe78606dc07d93dcedb30cf0bddf1829812143541b1ad39f' => 'Runner/TestSuiteLoader.php',
        '9e8806c684a23d14a9ed2d6fb3102c7be9a438e7e2b96ecfe3b634817e285104' => 'TextUI/Command/Commands/WarmCodeCoverageCacheCommand.php',
        'badc88c79c2a47d768be3925051999b158d08b64e57ccf4ce560f1610cbcc1e8' => 'TextUI/Output/Default/ProgressPrinter/Subscriber/TestSkippedSubscriber.php',
        '5ff38e143e244c4d80e767447e5a045891cc6518f008f24f2bb945289b83a07f' => 'TextUI/TestSuiteFilterProcessor.php',
        'a01a02eadd18146f12731c7adb8cd56cf76f3f6bda2bae06ff4fd6573789b0f4' => 'Event/Value/ThrowableBuilder.php',
        '354137e9f9489633cab805c1f1de4023f84c90e4cdfb36ac9bdc0c321dd7078d' => 'Logging/JUnit/JunitXmlLogger.php',
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
