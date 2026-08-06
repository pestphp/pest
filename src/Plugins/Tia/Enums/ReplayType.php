<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia\Enums;

use PHPUnit\Framework\TestStatus\TestStatus;

/**
 * @internal
 */
enum ReplayType
{
    case None;
    case Pass;
    case Risky;
    case Skipped;
    case Incomplete;
    case Failure;

    public static function fromStatus(?TestStatus $status): self
    {
        if (! $status instanceof TestStatus) {
            return self::None;
        }

        return match (true) {
            $status->isSuccess() => self::Pass,
            $status->isRisky() => self::Risky,
            $status->isSkipped() => self::Skipped,
            $status->isIncomplete() => self::Incomplete,
            // A recorded notice, deprecation or warning only reaches replay when
            // the configured failOn* / displayDetailsOn* policies say it is not
            // worth re-running — which means the test passed. Folding it into
            // Failure below would turn a green run red on cache alone.
            $status->isNotice(), $status->isDeprecation(), $status->isWarning() => self::Pass,
            $status->isFailure(), $status->isError() => self::Failure,
            default => self::None,
        };
    }
}
