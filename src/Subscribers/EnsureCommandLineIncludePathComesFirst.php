<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\TestRunner\EventFacadeSealed;
use PHPUnit\Event\TestRunner\EventFacadeSealedSubscriber;

final readonly class EnsureCommandLineIncludePathComesFirst implements EventFacadeSealedSubscriber
{
    /**
     * @var list<string>
     */
    private array $directories;

    public function __construct(string $includePath)
    {
        $this->directories = array_values(array_filter(
            explode(PATH_SEPARATOR, $includePath),
            fn (string $directory): bool => $directory !== '',
        ));
    }

    public function notify(EventFacadeSealed $event): void
    {
        $this->apply();
    }

    public function apply(): void
    {
        if ($this->directories === []) {
            return;
        }

        $otherDirectories = array_filter(
            explode(PATH_SEPARATOR, (string) get_include_path()),
            fn (string $directory): bool => ! in_array($directory, $this->directories, true),
        );

        ini_set('include_path', implode(PATH_SEPARATOR, [...$this->directories, ...$otherDirectories]));
    }
}
