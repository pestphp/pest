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
}
