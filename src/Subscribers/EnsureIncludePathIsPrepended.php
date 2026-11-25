<?php

declare(strict_types=1);

namespace Pest\Subscribers;

use PHPUnit\Event\TestRunner\EventFacadeSealed;
use PHPUnit\Event\TestRunner\EventFacadeSealedSubscriber;

final readonly class EnsureIncludePathIsPrepended implements EventFacadeSealedSubscriber
{
    public function __construct(private string $includePath) {}

    public function notify(EventFacadeSealed $event): void
    {
        $directories = array_filter(
            explode(PATH_SEPARATOR, $this->includePath),
            fn (string $directory): bool => $directory !== '',
        );

        if ($directories === []) {
            return;
        }

        ini_set('include_path', implode(PATH_SEPARATOR, $directories).PATH_SEPARATOR.get_include_path());
    }
}
