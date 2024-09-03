<?php

declare(strict_types=1);

namespace Pest\Collision;

use NunoMaduro\Collision\Adapters\Phpunit\TestResult;
use Pest\Configuration\Project;
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
            'assignees' => $assignees,
            'issues' => $issues,
            'prs' => $prs,
        ] = $context;

        if (($link = Project::getInstance()->issues) !== '') {
            $issuesDescription = array_map(fn (int $issue): string => sprintf('<a href="%s">#%s</a>', sprintf($link, $issue), $issue), $issues);
        }

        if (($link = Project::getInstance()->prs) !== '') {
            $prsDescription = array_map(fn (int $pr): string => sprintf('<a href="%s">#%s</a>', sprintf($link, $pr), $pr), $prs);
        }

        if (($link = Project::getInstance()->assignees) !== '' && count($assignees) > 0) {
            $assigneesDescription = array_map(fn (string $assignee): string => sprintf(
                '<a href="%s">@%s</a>',
                sprintf($link, $assignee),
                $assignee,
            ), $assignees);
        }

        if (count($assignees) > 0 || count($issues) > 0 || count($prs) > 0) {
            $description .= ' '.implode(', ', array_merge(
                $issuesDescription ?? [],
                $prsDescription ?? [],
                isset($assigneesDescription) ? ['['.implode(', ', $assigneesDescription).']'] : [],
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
