<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

use PHPUnit\Framework\TestStatus\TestStatus;

/**
 * @internal
 */
enum Replay
{
    case Pass;
    case Skipped;
    case Incomplete;
    case Failure;

    public static function from(TestStatus $cached): self
    {
        return match (true) {
            $cached->isSuccess(), $cached->isRisky() => self::Pass,
            $cached->isSkipped() => self::Skipped,
            $cached->isIncomplete() => self::Incomplete,
            default => self::Failure,
        };
    }
}
