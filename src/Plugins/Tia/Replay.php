<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use PHPUnit\Framework\TestStatus\TestStatus;

/**
 * @internal
 */
enum Replay
{
    case No;
    case Pass;
    case Skipped;
    case Incomplete;
    case Failure;

    public static function fromStatus(?TestStatus $status): self
    {
        if (! $status instanceof TestStatus) {
            return self::No;
        }

        return match (true) {
            $status->isSuccess(), $status->isRisky() => self::Pass,
            $status->isSkipped() => self::Skipped,
            $status->isIncomplete() => self::Incomplete,
            default => self::Failure,
        };
    }
}
