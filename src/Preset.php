<?php

declare(strict_types=1);

namespace Pest;

use Pest\Arch\Support\Composer;
use Pest\ArchPresets\AbstractPreset;
use Pest\ArchPresets\Base;
use Pest\ArchPresets\Strict;
use Pest\PendingCalls\TestCall;
use stdClass;

/**
 * @internal
 */
final class Preset
{
    /**
     * The application / package base namespaces.
     *
     * @var ?array<int, string>
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
    public function base(): Base
    {
        return $this->executePreset(new Base($this->baseNamespaces()));
    }

    /**
     * Uses the Pest strict preset and returns the test call instance.
     */
    public function strict(): Strict
    {
        return $this->executePreset(new Strict($this->baseNamespaces()));
    }

    /**
     * Executes the given preset.
     *
     * @template TPreset of AbstractPreset
     *
     * @param  TPreset  $preset
     * @return TPreset
     */
    private function executePreset(AbstractPreset $preset): AbstractPreset
    {
        if ((fn (): ?string => $this->description)->call($this->testCall) === null) {
            $description = strtolower((new \ReflectionClass($preset))->getShortName());

            (fn (): string => $this->description = sprintf('arch "%s" preset', $description))->call($this->testCall);
        }

        $this->baseNamespaces();

        $preset->execute();

        $this->testCall->testCaseMethod->closure = (function () use ($preset): void {
            $preset->flush();
        })->bindTo(new stdClass);

        return $preset;
    }

    /**
     * Get the base namespaces for the application / package.
     *
     * @return array<int, string>
     */
    private function baseNamespaces(): array
    {
        if (self::$baseNamespaces === null) {
            self::$baseNamespaces = Composer::userNamespaces();
        }

        return self::$baseNamespaces;
    }
}
