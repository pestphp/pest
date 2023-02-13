<?php

declare(strict_types=1);

namespace Pest;

use NunoMaduro\Collision\Writer;
use Pest\Support\Container;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Whoops\Exception\Inspector;

final class Panic
{
    /**
     * Creates a new Panic instance.
     */
    private function __construct(
        private readonly Throwable $throwable
    ) {
        // ...
    }

    /**
     * Creates a new Panic instance, and exits the application.
     */
    public static function with(Throwable $throwable): never
    {
        $panic = new self($throwable);

        $panic->handle();

        exit(1);
    }

    /**
     * Handles the panic.
     */
    private function handle(): void
    {
        /** @var OutputInterface $output */
        $output = Container::getInstance()->get(OutputInterface::class);

        if ($this->throwable instanceof Contracts\Panicable) {
            $this->throwable->render($output);

            exit($this->throwable->exitCode());
        }

        $writer = new Writer(null, $output);

        $inspector = new Inspector($this->throwable);

        $output->writeln('');
        $writer->write($inspector);
        $output->writeln('');

        exit(1);
    }
}
