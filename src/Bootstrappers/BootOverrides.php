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
        'c7b9c8a96006dea314204a8f09a8764e51ce0b9b79aadd58da52e8c328db4870' => 'Runner/Filter/NameFilterIterator.php',
        'c7c09ab7c9378710b27f761a4b2948196cbbdf2a73e4389bcdca1e7c94fa9c21' => 'Runner/ResultCache/DefaultResultCache.php',
        'bc8718c89264f65800beabc23e51c6d3bcff87dfc764a12179ef5dbfde272c8b' => 'Runner/TestSuiteLoader.php',
        'f41e48d6cb546772a7de4f8e66b6b7ce894a5318d063eb52e354d206e96c701c' => 'TextUI/Command/Commands/WarmCodeCoverageCacheCommand.php',
        'cb7519f2d82893640b694492cf7ec9528da80773cc1d259634181b5d393528b5' => 'TextUI/Output/Default/ProgressPrinter/Subscriber/TestSkippedSubscriber.php',
        '2f06e4b1a9f3a24145bfc7ea25df4f124117f940a2cde30a04d04d5678006bff' => 'TextUI/TestSuiteFilterProcessor.php',
        'ef64a657ed9c0067791483784944107827bf227c7e3200f212b6751876b99e25' => 'Event/Value/ThrowableBuilder.php',
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
