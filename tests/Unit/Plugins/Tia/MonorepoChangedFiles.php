<?php

declare(strict_types=1);

use Pest\Plugins\Tia\ChangedFiles;
use Symfony\Component\Process\Process;
use Tests\Fixtures\Tia\GitRepo;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/pest-tia-monorepo-changes-'.bin2hex(random_bytes(8));
    mkdir($this->root.'/apps/api/app', 0755, true);
    mkdir($this->root.'/apps/web', 0755, true);
    file_put_contents($this->root.'/apps/api/app/Service.php', "<?php\n\$service = 1;\n");

    $this->repo = new GitRepo($this->root);
    $this->repo->init();
    $this->sha = $this->repo->sha();
});

afterEach(function (): void {
    new Process(['rm', '-rf', $this->root])->mustRun();
});

it('detects committed Unicode filenames with git path quoting enabled', function (string $directory): void {
    $this->repo->config('core.quotePath', 'true');
    $project = $this->root.$directory;
    file_put_contents($project.'/Ærlig.php', "<?php\n\$value = 1;\n");
    $this->repo->commit('Add a Unicode filename');
    $sha = $this->repo->sha();

    file_put_contents($project.'/Ærlig.php', "<?php\n\$value = 2;\n");
    $this->repo->commit('Change the Unicode file');

    expect(new ChangedFiles($project)->since($sha))->toBe(['Ærlig.php']);
})->with([
    'repository root' => '',
    'nested project' => '/apps/api',
])->skipOnWindows();

it('reports the old path when a file moves outside the project', function (bool $committed): void {
    $this->repo->run(['mv', 'apps/api/app/Service.php', 'apps/web/Service.php']);

    if ($committed) {
        $this->repo->commit('Move the service into a sibling project');
    }

    expect(new ChangedFiles($this->root.'/apps/api')->since($this->sha))->toBe(['app/Service.php']);
})->with([
    'staged move' => false,
    'committed move' => true,
])->skipOnWindows();

it('ignores cosmetic changes when reading a nested project baseline', function (): void {
    file_put_contents($this->root.'/apps/api/app/Service.php', "<?php\n\$service   =   1;\n");
    $this->repo->commit('Change whitespace');

    expect(new ChangedFiles($this->root.'/apps/api')->since($this->sha))->toBe([]);
})->skipOnWindows();

it('includes untracked files inside the project', function (): void {
    file_put_contents($this->root.'/apps/api/app/Fresh.php', "<?php\n");
    file_put_contents($this->root.'/apps/web/Other.php', "<?php\n");

    expect(new ChangedFiles($this->root.'/apps/api')->since($this->sha))->toBe(['app/Fresh.php']);
})->skipOnWindows();
