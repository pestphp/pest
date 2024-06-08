<?php

declare(strict_types=1);

namespace Pest;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Arch\Support\Composer;
use Pest\PendingCalls\TestCall;

/**
 * @internal
 */
final class Preset
{
    /**
     * The application / package base namespaces.
     */
    private static ?array $baseNamespaces = null;

    /**
     * Creates a new preset instance.
     */
    public function __construct(private readonly TestCall $testCall)
    {
        //
    }

    /**
     * Uses the Pest base preset and returns the test call instance.
     */
    public function base(): TestCall|ArchExpectation
    {
        return (new ArchPresets\Base)->boot($this->testCall, $this->baseNamespaces());
    }

    /**
     * Uses the Pest strict preset and returns the test call instance.
     */
    public function strict(): TestCall
    {
        (new ArchPresets\Strict)->boot($this->testCall, $this->baseNamespaces());

        return $this->testCall;
    }

    /**
     * Get the base namespaces for the application / package.
     */
    private function baseNamespaces(): array
    {
        if (self::$baseNamespaces === null) {
            self::$baseNamespaces = Composer::userNamespaces();
        }

        return self::$baseNamespaces;
    }
}
