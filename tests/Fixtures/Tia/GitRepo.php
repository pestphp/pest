<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tia;

use Pest\Plugins\Tia\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A git repository the scenario tests drive.
 *
 * Hermetic by construction: every invocation neutralises the machine's global
 * and system config and carries its own committer identity. Without that, an
 * ambient `init.defaultBranch = main` would answer questions the scenario means
 * to leave unanswered, and rows like "resolves to nothing, degrades safely"
 * would pass by accident.
 *
 * @internal
 */
final readonly class GitRepo
{
    /**
     * @var array<string, string>
     */
    public const array ENV = [
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_SYSTEM' => '/dev/null',
        // `GIT_CONFIG_SYSTEM` does not cover every system-level file git reads:
        // Apple's git also loads one from inside Xcode, and it sets
        // `init.defaultBranch`. Only this suppresses all of them.
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_AUTHOR_NAME' => 'Pest Fixture',
        'GIT_AUTHOR_EMAIL' => 'fixture@pestphp.io',
        'GIT_COMMITTER_NAME' => 'Pest Fixture',
        'GIT_COMMITTER_EMAIL' => 'fixture@pestphp.io',
    ];

    public function __construct(public string $path) {}

    /**
     * Initialises the repository on `$branch` and commits everything in it.
     */
    public function init(string $branch = 'master'): void
    {
        $this->run(['init', '--quiet']);
        $this->run(['checkout', '--quiet', '-b', $branch]);
        $this->commit('Initial commit');
    }

    public function commit(string $message): void
    {
        $this->run(['add', '-A']);
        $this->run(['commit', '--quiet', '--allow-empty', '-m', $message]);
    }

    public function switchTo(string $branch, bool $new = false): void
    {
        $this->run($new ? ['checkout', '--quiet', '-b', $branch] : ['checkout', '--quiet', $branch]);
    }

    public function rename(string $from, string $to): void
    {
        $this->run(['branch', '-m', $from, $to]);
    }

    public function detach(): void
    {
        $this->run(['checkout', '--quiet', '--detach']);
    }

    /**
     * Registers an `origin`, which also decides the graph's storage key:
     * {@see Storage::projectKey()} prefers the remote's
     * identity over the path, so two checkouts of one repository — a worktree,
     * say — share a single graph.
     */
    public function addOrigin(string $url = 'git@github.com:pestphp/tia-fixture.git'): void
    {
        $this->run(['remote', 'add', 'origin', $url]);
    }

    public function removeOrigin(): void
    {
        $this->run(['remote', 'remove', 'origin']);
    }

    /**
     * Points `refs/remotes/origin/HEAD` at a local branch — what
     * `git remote set-head` would write, without a remote to talk to.
     */
    public function setOriginHead(string $branch): void
    {
        $this->run(['update-ref', 'refs/remotes/origin/'.$branch, 'HEAD']);
        $this->run(['symbolic-ref', 'refs/remotes/origin/HEAD', 'refs/remotes/origin/'.$branch]);
    }

    /**
     * Drops `refs/remotes/origin/HEAD` while keeping the remote-tracking branch
     * — what a CI checkout looks like. `actions/checkout` builds its working
     * copy with `git init` plus a single-ref `fetch` rather than a `clone`, and
     * only a `clone` writes that symbolic ref.
     */
    public function unsetOriginHead(): void
    {
        $this->run(['symbolic-ref', '--delete', 'refs/remotes/origin/HEAD']);
    }

    public function config(string $key, string $value): void
    {
        $this->run(['config', '--local', $key, $value]);
    }

    /**
     * Adds a worktree for a new branch and returns its path.
     */
    public function worktree(string $path, string $branch): string
    {
        $this->run(['worktree', 'add', '--quiet', '-b', $branch, $path]);

        return $path;
    }

    public function sha(): string
    {
        return $this->output(['rev-parse', 'HEAD']);
    }

    public function currentBranch(): string
    {
        return $this->output(['rev-parse', '--abbrev-ref', 'HEAD']);
    }

    /**
     * @return array<int, string>
     */
    public function branchNames(): array
    {
        $names = explode("\n", $this->output(['for-each-ref', '--format=%(refname:short)', 'refs/heads']));

        return array_values(array_filter($names, fn (string $name): bool => $name !== ''));
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public function output(array $arguments): string
    {
        return trim($this->process($arguments, mustSucceed: true)->getOutput());
    }

    /**
     * @param  array<int, string>  $arguments
     */
    public function run(array $arguments): void
    {
        $this->process($arguments, mustSucceed: true);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function process(array $arguments, bool $mustSucceed): Process
    {
        $process = new Process(['git', ...$arguments], $this->path, self::ENV);
        $process->setTimeout(30.0);
        $process->run();

        if ($mustSucceed && ! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                "git %s failed in [%s]:\n%s",
                implode(' ', $arguments),
                $this->path,
                $process->getErrorOutput().$process->getOutput(),
            ));
        }

        return $process;
    }
}
