<?php

declare(strict_types=1);

namespace Pest\Tests\ParallelRunnerWarning;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade as ExtensionFacade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class SubscribingExtension implements Extension
{
    public function bootstrap(Configuration $configuration, ExtensionFacade $facade, ParameterCollection $parameters): void
    {
        EventFacade::instance()->registerSubscriber(new class implements PreparedSubscriber {
            public function notify(Prepared $event): void
            {
            }
        });
    }
}
