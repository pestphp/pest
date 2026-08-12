<?php

declare(strict_types=1);

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\TextUI\Output\Default\ProgressPrinter;

use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use ReflectionClass;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final readonly class TestSkippedSubscriber extends Subscriber implements SkippedSubscriber
{
    public function notify(Skipped $event): void
    {
        if ($event->message() === '__TODO__') {
            $this->printTodoItem();
        }

        $this->printer()->testSkipped();
    }

    private function printTodoItem(): void
    {
        $mirror = new ReflectionClass($this->printer());
        $printProgress = $mirror->getMethod('printProgress');
        $printProgress->invoke($this->printer(), 'T');
    }
}
