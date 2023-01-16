<?php

declare(strict_types=1);

namespace Pest\Exporters;

use SebastianBergmann\Exporter\Exporter as BaseExporter;
use SebastianBergmann\RecursionContext\Context;

/**
 * @internal
 */
final class Exporter extends BaseExporter
{
    /**
     * Exports a value into a single-line string recursively.
     */
    public function shortenedRecursiveExport(&$data, Context $context = null)
    {
        $result   = [];
        $array    = $data;
        $exporter = new static();
        $context  = $context ?? new Context();

        $context->add($data);

        foreach ($array as $key => $value) {
            if (!is_array($value)) {
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
        return (string) preg_replace(['#\.{3}#', '#\\\n\s*#'], ['…'], parent::shortenedExport($value));
    }
}
