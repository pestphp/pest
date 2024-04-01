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
        'c7c09ab7c9378710b27f761a4b2948196cbbdf2a73e4389bcdca1e7c94fa9c21' => 'Runner/ResultCache/DefaultResultCache.php',
        'bc8718c89264f65800beabc23e51c6d3bcff87dfc764a12179ef5dbfde272c8b' => 'Runner/TestSuiteLoader.php',
        '2ef8e21dbb27cf6597dd9bb0f941c063dcc98b5af2c35d10b1c2d77721582e8f' => 'TextUI/Command/Commands/WarmCodeCoverageCacheCommand.php',
        'badc88c79c2a47d768be3925051999b158d08b64e57ccf4ce560f1610cbcc1e8' => 'TextUI/Output/Default/ProgressPrinter/Subscriber/TestSkippedSubscriber.php',
        '5ff38e143e244c4d80e767447e5a045891cc6518f008f24f2bb945289b83a07f' => 'TextUI/TestSuiteFilterProcessor.php',
        'a01a02eadd18146f12731c7adb8cd56cf76f3f6bda2bae06ff4fd6573789b0f4' => 'Event/Value/ThrowableBuilder.php',
        'c78f96e34b98ed01dd8106539d59b8aa8d67f733274118b827c01c5c4111c033' => 'Logging/JUnit/JunitXmlLogger.php',
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
