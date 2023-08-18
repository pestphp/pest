<?php

declare(strict_types=1);

namespace Pest\Support;

use Symfony\Component\Console\Output\OutputInterface;
use Termwind\Termwind;

use function Termwind\render;
use function Termwind\renderUsing;

/**
 * @internal
 */
final class View
{
    /**
     * The implementation of the output.
     */
    private static OutputInterface $output;

    /**
     * Renders views using the given Output instance.
     */
    public static function renderUsing(OutputInterface $output): void
    {
        self::$output = $output;
    }

    /**
     * Renders the given view.
     *
     * @param  array<string, mixed>  $data
     */
    public static function render(string $path, array $data = []): void
    {
        $contents = self::compile($path, $data);

        $existing = Termwind::getRenderer();

        renderUsing(self::$output);

        try {
            render($contents);
        } finally {
            renderUsing($existing);
        }
    }

    /**
     * Compiles the given view.
     *
     * @param  array<string, mixed>  $data
     */
    private static function compile(string $path, array $data): string
    {
        extract($data);

        ob_start();

        $path = str_replace('.', '/', $path);

        include sprintf('%s/../../resources/views/%s.php', __DIR__, $path);

        $contents = ob_get_contents();

        ob_clean();

        return (string) $contents;
    }
}
