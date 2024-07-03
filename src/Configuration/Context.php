<?php

declare(strict_types=1);

namespace Pest\Configuration;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;

/**
 * @internal
 */
final readonly class Context
{
    /**
     * Sets the test context to GitHub.
     */
    public function github(string $project): self
    {
        DefaultPrinter::linkIssuesWith("https://github.com/{$project}/issues/%s");
        DefaultPrinter::linkPrsWith("https://github.com/{$project}/pull/%s");

        return $this;
    }

    /**
     * Sets the test context to GitLab.
     */
    public function gitlab(string $project): self
    {
        DefaultPrinter::linkIssuesWith("https://gitlab.com/{$project}/issues/%s");
        DefaultPrinter::linkPrsWith("https://gitlab.com/{$project}/merge_requests/%s");

        return $this;
    }

    /**
     * Sets the test context to Bitbucket.
     */
    public function bitbucket(string $project): self
    {
        DefaultPrinter::linkIssuesWith('https://bitbucket.org/{$project}/issues/%s');
        DefaultPrinter::linkPrsWith("https://bitbucket.org/{$project}/pull-requests/%s");

        return $this;
    }

    /**
     * Sets the test context to Jira.
     */
    public function jira(string $namespace, string $project): self
    {
        DefaultPrinter::linkIssuesWith("https://{$namespace}.atlassian.net/browse/{$project}-%s");

        return $this;
    }

    /**
     * Sets the test context to custom.
     */
    public function using(string $issues, string $prs): self
    {
        DefaultPrinter::linkIssuesWith($issues);
        DefaultPrinter::linkPrsWith($prs);

        return $this;
    }
}
