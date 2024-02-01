<?php

use Pest\Support\DatasetInfo;

it('can check if dataset is defined inside a Datasets directory', function (string $file, bool $inside) {
    expect(DatasetInfo::isInsideADatasetsDirectory($file))->toBe($inside);
})->with([
    ['file' => '/var/www/project/tests/Datasets/Numbers.php', 'inside' => true],
    ['file' => '/var/www/project/tests/Datasets.php', 'inside' => false],
    ['file' => '/var/www/project/tests/Features/Datasets/Numbers.php', 'inside' => true],
    ['file' => '/var/www/project/tests/Features/Numbers.php', 'inside' => false],
    ['file' => '/var/www/project/tests/Features/Datasets.php', 'inside' => false],
]);

it('can check if dataset is defined inside a Datasets.php file', function (string $file, bool $inside) {
    expect(DatasetInfo::isADatasetsFile($file))->toBe($inside);
})->with([
    ['file' => '/var/www/project/tests/Datasets/Numbers.php', 'inside' => false],
    ['file' => '/var/www/project/tests/Datasets.php', 'inside' => true],
    ['file' => '/var/www/project/tests/Features/Datasets/Numbers.php', 'inside' => false],
    ['file' => '/var/www/project/tests/Features/Numbers.php', 'inside' => false],
    ['file' => '/var/www/project/tests/Features/Datasets.php', 'inside' => true],
]);

it('computes the dataset scope', function (string $file, string $scope) {
    expect(DatasetInfo::scope($file))->toBe($scope);
})->with([
    ['file' => '/var/www/project/tests/Datasets/Numbers.php', 'scope' => '/var/www/project/tests'],
    ['file' => '/var/www/project/tests/Datasets.php', 'scope' => '/var/www/project/tests'],
    ['file' => '/var/www/project/tests/Features/Datasets/Numbers.php', 'scope' => '/var/www/project/tests/Features'],
    ['file' => '/var/www/project/tests/Features/Numbers.php', 'scope' => '/var/www/project/tests/Features/Numbers.php'],
    ['file' => '/var/www/project/tests/Features/Datasets.php', 'scope' => '/var/www/project/tests/Features'],
    ['file' => '/var/www/project/tests/Features/Controllers/Datasets/Numbers.php', 'scope' => '/var/www/project/tests/Features/Controllers'],
    ['file' => '/var/www/project/tests/Features/Controllers/Numbers.php', 'scope' => '/var/www/project/tests/Features/Controllers/Numbers.php'],
    ['file' => '/var/www/project/tests/Features/Controllers/Datasets.php', 'scope' => '/var/www/project/tests/Features/Controllers'],
]);
