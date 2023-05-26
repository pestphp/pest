<?php

declare(strict_types=1);

namespace Pest;

use Pest\PendingCalls\DescribeCall;

/**
 * @internal
 */
final class PendingCalls
{
    /**
     * The current describe call.
     */
    public static ?string $describing = null;

    /**
     * Sets the current describe call.
     */
    public static function describe(DescribeCall $describeCall): void
    {

    }
}
