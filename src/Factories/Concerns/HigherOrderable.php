<?php

declare(strict_types=1);

namespace Pest\Factories\Concerns;

use Pest\Support\HigherOrderMessageCollection;

trait HigherOrderable
{
    public HigherOrderMessageCollection $chains;

    public HigherOrderMessageCollection $factoryProxies;

    public HigherOrderMessageCollection $proxies;

    private function bootHigherOrderable(): void
    {
        $this->chains = new HigherOrderMessageCollection;
        $this->factoryProxies = new HigherOrderMessageCollection;
        $this->proxies = new HigherOrderMessageCollection;
    }
}
