<?php declare(strict_types=1);
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
 *
 * This file is only overridden when using Pest Parallel. See /bin/worker.php for more information.
 */
final class TestSkippedSubscriber extends Subscriber implements SkippedSubscriber
{
    /**
     * Notifies the printer that a test was skipped.
     */
    public function notify(Skipped $event): void
    {
        str_contains($event->message(), '__TODO__')
            ? $this->printTodoItem()
            : $this->printer()->testSkipped();
    }

    /**
     * Prints a "T" to the standard PHPUnit output to indicate a todo item.
     */
    private function printTodoItem(): void
    {
        $mirror = new ReflectionClass($this->printer());
        $printerMirror = $mirror->getMethod('printProgress');
        $printerMirror->invoke($this->printer(), 'T');
    }
}
