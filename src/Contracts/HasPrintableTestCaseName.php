<?php

declare(strict_types=1);

namespace Pest\Contracts;

if (interface_exists(\NunoMaduro\Collision\Contracts\Adapters\Phpunit\HasPrintableTestCaseName::class)) {
    /**
     * @internal
     */
    interface HasPrintableTestCaseName extends \NunoMaduro\Collision\Contracts\Adapters\Phpunit\HasPrintableTestCaseName
    {
    }
} else {
    /**
     * @internal
     */
    interface HasPrintableTestCaseName
    {
    }
}
