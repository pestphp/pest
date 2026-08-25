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
    private const int MAX_ARRAY_ITEMS = 3;

    public function __construct(
        private BaseExporter $exporter,
    ) {
        //
    }

    public static function default(): self
    {
        return new self(
            new BaseExporter
        );
    }

    /**
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
                // @phpstan-ignore-next-line
                : sprintf('[%s]', $this->shortenedRecursiveExport($data[$key], $context));
        }

        return implode(', ', $result);
    }

    public function shortenedExport(mixed $value): string
    {
        $map = [
            '#\.{3}#' => '…',
            '#\\\n\s*#' => '',
            '# Object \(…\)#' => '',
        ];

        return (string) preg_replace(array_keys($map), array_values($map), $this->exporter->shortenedExport($value));
    }

    public function export(mixed $value): string
    {
        $map = [
            '#\\\n\s*#' => '',
            '# Object \(\.{3}\)#' => '',
        ];

        return (string) preg_replace(array_keys($map), array_values($map), $this->exporter->export($value));
    }
}
