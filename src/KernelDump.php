<?php

declare(strict_types=1);

namespace Pest;

use Laravel\Pao\Execution;
use Pest\Support\View;
use Symfony\Component\Console\Output\OutputInterface;

final class KernelDump
{
    private string $buffer = '';

    public function __construct(
        private readonly OutputInterface $output,
    ) {
        //
    }

    public function enable(): void
    {
        if (class_exists(Execution::class) && Execution::running()) {
            return;
        }

        ob_start(function (string $message): string {
            $this->buffer .= $message;

            return '';
        });
    }

    public function disable(): void
    {
        @ob_clean();

        if ($this->buffer !== '') {
            $this->flush();
        }
    }

    public function terminate(): void
    {
        $this->disable();
    }

    private function flush(): void
    {
        View::renderUsing($this->output);

        if ($this->isOpeningHeadline($this->buffer)) {
            $this->buffer = implode(PHP_EOL, array_slice(explode(PHP_EOL, $this->buffer), 2));
        }

        $type = 'INFO';

        if (is_array($error = error_get_last()) && in_array($error['type'], [E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
            return;
        }

        if ($this->isInternalError($this->buffer)) {
            $type = 'ERROR';
            $this->buffer = str_replace(
                sprintf('An error occurred inside PHPUnit.%s%sMessage:  ', PHP_EOL, PHP_EOL), '', $this->buffer,
            );
        }

        $this->buffer = trim($this->buffer);
        $this->buffer = rtrim($this->buffer, '.').'.';

        $lines = explode(PHP_EOL, $this->buffer);

        $lines = array_reverse($lines);
        $firstLine = array_pop($lines);
        $lines = array_reverse($lines);

        View::render('components.badge', [
            'type' => $type,
            'content' => $firstLine,
        ]);

        $this->output->writeln($lines);

        $this->buffer = '';
    }

    private function isOpeningHeadline(string $output): bool
    {
        return str_contains($output, 'by Sebastian Bergmann and contributors.');
    }

    private function isInternalError(string $output): bool
    {
        return str_contains($output, 'An error occurred inside PHPUnit.');
    }
}
