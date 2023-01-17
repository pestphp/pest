<?php

declare(strict_types=1);

namespace Pest\Exporters;

use SebastianBergmann\Exporter\Exporter as BaseExporter;
use SebastianBergmann\RecursionContext\Context;

/**
 * @internal
 */
final class Exporter
{
    /**
     * The maximum number of items in an array to export.
     */
    private const MAX_ARRAY_ITEMS = 3;

    /**
     * The PHPUnit exporter.
     */
    private readonly BaseExporter $exporter;

    /**
     * Instantiate the class.
     */
    public function __construct()
    {
        $this->exporter = new BaseExporter();
    }

    /**
     * Exports a value into a single-line string recursively.
     *
     * @param  array<int|string, mixed>  $data
     */
    public function shortenedRecursiveExport(array &$data, Context $context = null): string
    {
        $result = [];
        $array = $data;
        $itemsCount = 0;
        $exporter = new self();
        $context ??= new Context();

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
        return (string) preg_replace(['#\.{3}#', '#\\\n\s*#'], ['…'], $this->exporter->shortenedExport($value));
    }
}
