<?php

declare(strict_types=1);

namespace Pest\Factories\Concerns;

use Pest\Support\HigherOrderMessageCollection;

trait HigherOrderable
{
    /**
     * The higher order messages that are chainable.
     */
    public HigherOrderMessageCollection $chains;

    /**
     * The higher order messages that are "factory" proxyable.
     */
    public HigherOrderMessageCollection $factoryProxies;

    /**
     * The higher order messages that are proxyable.
     */
    public HigherOrderMessageCollection $proxies;

    /**
     * Boot the higher order properties.
     */
    private function bootHigherOrderable(): void
    {
        $this->chains = new HigherOrderMessageCollection();
        $this->factoryProxies = new HigherOrderMessageCollection();
        $this->proxies = new HigherOrderMessageCollection();
    }
}
