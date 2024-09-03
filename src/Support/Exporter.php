<?php

declare(strict_types=1);

namespace Pest\Support;

use SebastianBergmann\Exporter\Exporter as BaseExporter;
use SebastianBergmann\RecursionContext\Context;

/**
 * @internal
 */
final readonly class Exporter
{
    /**
     * The maximum number of items in an array to export.
     */
    private const MAX_ARRAY_ITEMS = 3;

    /**
     * Creates a new Exporter instance.
     */
    public function __construct(
        private BaseExporter $exporter,
    ) {
        // ...
    }

    /**
     * Creates a new Exporter instance.
     */
    public static function default(): self
    {
        return new self(
            new BaseExporter
        );
    }

    /**
     * Exports a value into a single-line string recursively.
     *
     * @param  array<int|string, mixed>  $data
     */
    public function shortenedRecursiveExport(array &$data, ?Context $context = null): string
    {
        $result = [];
        $array = $data;
        $itemsCount = 0;
        $exporter = self::default();
        $context ??= new Context;

        $context->add($data);

        foreach ($array as $key => $value) {
            if (++$itemsCount > self::MAX_ARRAY_ITEMS) {
                $result[] = '…';

                break;
            }

            if (! is_array($value)) {
                $result[] = $exporter->shortenedExport($value);

                continue;
            }

            $result[] = $context->contains($data[$key]) !== false
                ? '*RECURSION*'
                : sprintf('[%s]', $this->shortenedRecursiveExport($data[$key], $context));
        }

        return implode(', ', $result);
    }

    /**
     * Exports a value into a single-line string.
     */
    public function shortenedExport(mixed $value): string
    {
        $map = [
            '#\.{3}#' => '…',
            '#\\\n\s*#' => '',
            '# Object \(…\)#' => '',
        ];

        return (string) preg_replace(array_keys($map), array_values($map), $this->exporter->shortenedExport($value));
    }
}
