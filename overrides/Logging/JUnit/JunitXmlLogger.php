<?php

declare(strict_types=1);

namespace PHPUnit\Logging\JUnit;

use PHPUnit\Event\Facade;
use PHPUnit\TextUI\Output\Printer;

final class JunitXmlLogger
{
    public function __construct(Printer $printer, Facade $facade)
    {
        /** @see \Pest\Logging\JUnit\JUnitLogger */
    }
}
