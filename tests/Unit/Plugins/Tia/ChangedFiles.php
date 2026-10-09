<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ChangedFiles;
use Pest\Plugins\Tia\Fingerprint;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\GitRepo;

/**
 * @return array<int, string>
 */
function tiaMonorepoRoots(?string $created = null): array
{
    static $roots = [];

    if ($created !== null) {
        $roots[] = $created;

        return $roots;
    }

    $all = $roots;
    $roots = [];

    return $all;
}

/**
 * @return array{root: string, project: string, repo: GitRepo, sha: string}
 */
function tiaMonorepoRepository(): array
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-tia-monorepo-'.bin2hex(random_bytes(8));

    mkdir($root.'/backend/app', 0755, true);
    mkdir($root.'/frontend', 0755, true);

    file_put_contents($root.'/backend/app/Service.php', "<?php\n\$service = 1;\n");
    file_put_contents($root.'/frontend/widget.php', "<?php\n\$widget = 1;\n");

    tiaMonorepoRoots($root);

    $repo = new GitRepo($root);
    $repo->init('master');

    return ['root' => $root, 'project' => $root.'/backend', 'repo' => $repo, 'sha' => $repo->sha()];
}

afterEach(function (): void {
    foreach (tiaMonorepoRoots() as $root) {
        new Process(['rm', '-rf', $root])->run();
    }
});

it('resolves the project prefix inside the repository', function (): void {
    $monorepo = tiaMonorepoRepository();

    expect(new ChangedFiles($monorepo['project'])->gitPrefix())->toBe('backend/')
        ->and(new ChangedFiles($monorepo['root'])->gitPrefix())->toBeEmpty();
})->skipOnWindows();

it('reports working tree changes relative to the project, dropping siblings', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['project'].'/app/Service.php', "<?php\n\$service = 2;\n");
    file_put_contents($monorepo['root'].'/frontend/widget.php', "<?php\n\$widget = 2;\n");

    expect(new ChangedFiles($monorepo['project'])->since($monorepo['sha']))
        ->toBe(['app/Service.php']);
})->skipOnWindows();

it('reports committed changes relative to the project, dropping siblings', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['project'].'/app/Service.php', "<?php\n\$service = 2;\n");
    file_put_contents($monorepo['root'].'/frontend/widget.php', "<?php\n\$widget = 2;\n");

    $monorepo['repo']->commit('Second commit');

    expect(new ChangedFiles($monorepo['project'])->since($monorepo['sha']))
        ->toBe(['app/Service.php']);
})->skipOnWindows();

it('drops a file whose content came back to its baseline across the prefix boundary', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['project'].'/app/Service.php', "<?php\n\$service = 2;\n");
    $monorepo['repo']->commit('Change the service');

    file_put_contents($monorepo['project'].'/app/Service.php', "<?php\n\$service = 1;\n");
    $monorepo['repo']->commit('Put the service back');

    expect(new ChangedFiles($monorepo['project'])->since($monorepo['sha']))->toBe([]);
})->skipOnWindows();

it('honours a gitignore inside the project', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['project'].'/.gitignore', "/ignored.php\n");
    file_put_contents($monorepo['project'].'/ignored.php', "<?php\n");
    file_put_contents($monorepo['project'].'/app/Service.php', "<?php\n\$service = 2;\n");

    expect(new ChangedFiles($monorepo['project'])->since($monorepo['sha']))
        ->toContain('app/Service.php')
        ->not->toContain('ignored.php');
})->skipOnWindows();

it('leaves a project at the repository root untouched', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['root'].'/frontend/widget.php', "<?php\n\$widget = 2;\n");

    expect(new ChangedFiles($monorepo['root'])->since($monorepo['sha']))
        ->toBe(['frontend/widget.php']);
})->skipOnWindows();

it('reports the changes it left outside the project', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['project'].'/app/Service.php', "<?php\n\$service = 2;\n");
    file_put_contents($monorepo['root'].'/frontend/widget.php', "<?php\n\$widget = 2;\n");

    $changedFiles = new ChangedFiles($monorepo['project']);
    $changedFiles->since($monorepo['sha']);

    expect($changedFiles->outsideProject())->toBe(['frontend/widget.php']);
})->skipOnWindows();

it('reports nothing outside the project for a project at the repository root', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['root'].'/frontend/widget.php', "<?php\n\$widget = 2;\n");

    $changedFiles = new ChangedFiles($monorepo['root']);
    $changedFiles->since($monorepo['sha']);

    expect($changedFiles->outsideProject())->toBeEmpty();
})->skipOnWindows();

it('ties a fingerprint to the project location inside the repository', function (): void {
    $monorepo = tiaMonorepoRepository();

    mkdir($monorepo['root'].'/admin/app', 0755, true);
    file_put_contents($monorepo['root'].'/admin/app/Service.php', "<?php\n\$service = 1;\n");
    $monorepo['repo']->commit('add a second project');

    $nested = Fingerprint::compute($monorepo['project']);
    $sibling = Fingerprint::compute($monorepo['root'].'/admin');
    $root = Fingerprint::compute($monorepo['root']);

    expect($nested['structural']['project_prefix'])->toBe('backend/')
        ->and($sibling['structural']['project_prefix'])->toBe('admin/')
        ->and($root['structural'])->not->toHaveKey('project_prefix')
        ->and(Fingerprint::structuralMatches($nested, $sibling))->toBeFalse()
        ->and(Fingerprint::structuralMatches($nested, $root))->toBeFalse()
        ->and(Fingerprint::structuralDrift($sibling, $nested))->toContain('project_prefix');
})->skipOnWindows();

it('sees a committed non-ascii path that git would otherwise quote', function (): void {
    $monorepo = tiaMonorepoRepository();

    mkdir($monorepo['project'].'/resources/views', 0755, true);
    file_put_contents($monorepo['project'].'/resources/views/\u{26a1}orders.blade.php', "<div>a</div>\n");
    $monorepo['repo']->commit('add a lightning view');

    $sha = $monorepo['repo']->sha();

    file_put_contents($monorepo['project'].'/resources/views/\u{26a1}orders.blade.php', "<div>b</div>\n");
    $monorepo['repo']->commit('reword the lightning view');

    $changedFiles = new ChangedFiles($monorepo['project']);

    expect($changedFiles->since($sha))->toBe(['resources/views/\u{26a1}orders.blade.php'])
        ->and($changedFiles->outsideProject())->toBeEmpty();
})->skipOnWindows();

it('sees a committed non-ascii path at the repository root too', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['root'].'/frontend/\u{26a1}widget.php', "<?php\n\$widget = 1;\n");
    $monorepo['repo']->commit('add a lightning widget');

    $sha = $monorepo['repo']->sha();

    file_put_contents($monorepo['root'].'/frontend/\u{26a1}widget.php', "<?php\n\$widget = 2;\n");
    $monorepo['repo']->commit('rework the lightning widget');

    expect(new ChangedFiles($monorepo['root'])->since($sha))
        ->toBe(['frontend/\u{26a1}widget.php']);
})->skipOnWindows();

it('keeps a tracked outside change and drops an untracked ignored one', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['root'].'/.gitignore', "dist/\n");
    mkdir($monorepo['root'].'/frontend/dist', 0755, true);
    file_put_contents($monorepo['root'].'/frontend/dist/bundle.js', "var a = 1;\n");
    $monorepo['repo']->run(['add', '-f', 'frontend/dist/bundle.js']);
    $monorepo['repo']->commit('track a built bundle that the ignore rules match');

    $sha = $monorepo['repo']->sha();

    file_put_contents($monorepo['root'].'/frontend/dist/bundle.js', "var a = 2;\n");
    file_put_contents($monorepo['root'].'/frontend/dist/untracked.js', "var b = 1;\n");
    $monorepo['repo']->commit('rebuild the bundle');

    $changedFiles = new ChangedFiles($monorepo['project']);
    $changedFiles->since($sha);

    expect($changedFiles->outsideProject())->toBe(['frontend/dist/bundle.js']);
})->skipOnWindows();

it('keeps a tracked project change that the ignore rules match', function (): void {
    $monorepo = tiaMonorepoRepository();

    file_put_contents($monorepo['root'].'/.gitignore', "build/\n");
    mkdir($monorepo['project'].'/build', 0755, true);
    file_put_contents($monorepo['project'].'/build/Generated.php', "<?php\n\$built = 1;\n");
    $monorepo['repo']->run(['add', '-f', 'backend/build/Generated.php']);
    $monorepo['repo']->commit('track a generated file that the ignore rules match');

    $sha = $monorepo['repo']->sha();

    file_put_contents($monorepo['project'].'/build/Generated.php', "<?php\n\$built = 2;\n");
    file_put_contents($monorepo['project'].'/build/Untracked.php', "<?php\n");
    $monorepo['repo']->commit('regenerate the file');

    expect(new ChangedFiles($monorepo['project'])->since($sha))
        ->toBe(['build/Generated.php']);
})->skipOnWindows();

it('reads committed paths from the repository root even when git is told to be relative', function (): void {
    $monorepo = tiaMonorepoRepository();

    $monorepo['repo']->run(['config', 'diff.relative', 'true']);

    file_put_contents($monorepo['project'].'/app/Service.php', "<?php\n\$service = 2;\n");
    file_put_contents($monorepo['root'].'/frontend/widget.php', "<?php\n\$widget = 2;\n");
    $monorepo['repo']->commit('rework both sides');

    $changedFiles = new ChangedFiles($monorepo['project']);

    expect($changedFiles->since($monorepo['sha']))->toBe(['app/Service.php'])
        ->and($changedFiles->outsideProject())->toBe(['frontend/widget.php']);
})->skipOnWindows();
