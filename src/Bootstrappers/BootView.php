<?php

declare(strict_types=1);

namespace Pest\Bootstrappers;

use Closure;
use Pest\Contracts\Bootstrapper;
use Pest\Support\View;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * @internal
 */
final readonly class BootView implements Bootstrapper
{
    public function __construct(
        private OutputInterface $output
    ) {
        //
    }

    public function boot(): void
    {
        View::renderUsing($this->output);

        if (getenv('COLUMNS') === false && property_exists(Terminal::class, 'width') && property_exists(Terminal::class, 'height')) {
            (new Terminal)->getWidth();

            Closure::bind(static function (): void {
                Terminal::$width ??= 0;
                Terminal::$height ??= 0;
            }, null, Terminal::class)();
        }
    }
}
