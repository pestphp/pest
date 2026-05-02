<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use Pest\Concerns\Testable;
use Pest\Exceptions\TiaRequiresPestTests;
use Pest\Panic;
use Pest\Plugins\Tia\Recorder;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use ReflectionClass;

/**
 * @internal
 */
final readonly class EnsureTiaIsRunningPestTestsOnly implements PreparedSubscriber
{
    public function __construct(private Recorder $recorder) {}

    public function notify(Prepared $event): void
    {
        if (! $this->recorder->isActive()) {
            return;
        }

        $test = $event->test();

        if (! $test instanceof TestMethod) {
            return;
        }

        $className = $test->className();

        if (! class_exists($className, false)) {
            return;
        }

        if ($this->usesTestableTrait($className)) {
            return;
        }

        Panic::with(new TiaRequiresPestTests($className, $test->file()));
    }

    private function usesTestableTrait(string $className): bool
    {
        $reflection = new ReflectionClass($className);

        do {
            foreach ($reflection->getTraitNames() as $trait) {
                if ($trait === Testable::class) {
                    return true;
                }
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return false;
    }
}
