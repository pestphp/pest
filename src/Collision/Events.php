<?php

declare(strict_types=1);

namespace Pest\Collision;

use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use Pest\Configuration\Context;
use Symfony\Component\Console\Output\OutputInterface;

use function Termwind\render;
use function Termwind\renderUsing;

/**
 * @internal
 */
final class Events
{
    /**
     * Sets the output.
     */
    private static ?OutputInterface $output = null;

    /**
     * Sets the output.
     */
    public static function setOutput(OutputInterface $output): void
    {
        self::$output = $output;
    }

    /**
     * Fires before the test method description is printed.
     */
    public static function beforeTestMethodDescription(TestResult $result, string $description): string
    {
        if (($context = $result->context) === []) {
            return $description;
        }

        renderUsing(self::$output);

        [
            'issues' => $issues,
            'prs' => $prs,
        ] = $context;

        if ((($link = Context::getInstance()->issues) !== '' && ($link = Context::getInstance()->issues) !== '0')) {
            $issuesDescription = array_map(fn (int $issue): string => sprintf('<a href="%s">#%s</a>', sprintf($link, $issue), $issue), $issues);
        }

        if ((($link = Context::getInstance()->prs) !== '' && ($link = Context::getInstance()->prs) !== '0')) {
            $prsDescription = array_map(fn (int $pr): string => sprintf('<a href="%s">#%s</a>', sprintf($link, $pr), $pr), $prs);
        }

        if (count($issues) > 0 || count($prs) > 0) {
            $description .= ' '.implode(', ', array_merge(
                $issuesDescription ?? [],
                $prsDescription ?? [],
            ));
        }

        return $description;
    }

    /**
     * Fires after the test method description is printed.
     */
    public static function afterTestMethodDescription(TestResult $result): void
    {
        if (($context = $result->context) === []) {
            return;
        }

        renderUsing(self::$output);

        [
            'notes' => $notes,
        ] = $context;

        foreach ($notes as $note) {
            render(sprintf(<<<'HTML'
                <div class="ml-2">
                    <span class="text-gray"> // %s</span>
                </div>
                HTML, $note,
            ));
        }
    }
}
