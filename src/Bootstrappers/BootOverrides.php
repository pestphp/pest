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
        'ec723a9efae521dd6576d2e7d746cc12d3e27f271c49c46420fba8a0e161a61f' => 'Runner/Filter/NameFilterIterator.php',
        '52b2574e96269aca1bb2d41bbf418c3bcf23dd21d14c66f90789025c309e39df' => 'Runner/ResultCache/DefaultResultCache.php',
        'bc8718c89264f65800beabc23e51c6d3bcff87dfc764a12179ef5dbfde272c8b' => 'Runner/TestSuiteLoader.php',
        '2ef8e21dbb27cf6597dd9bb0f941c063dcc98b5af2c35d10b1c2d77721582e8f' => 'TextUI/Command/Commands/WarmCodeCoverageCacheCommand.php',
        'badc88c79c2a47d768be3925051999b158d08b64e57ccf4ce560f1610cbcc1e8' => 'TextUI/Output/Default/ProgressPrinter/Subscriber/TestSkippedSubscriber.php',
        '8607a62825a762735721c39e5d7d59f6efee1409d85cd3b1a976d0d83a308be0' => 'TextUI/TestSuiteFilterProcessor.php',
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
