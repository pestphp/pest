<?php

dataset('with_arguments', function (int $count = 3, bool $uppercase = false, string $except = '') {
    $dataset = [
        'foo'    => ['name' => 'bar', 'title' => 'seee', 'except' => $except],
        'baz'    => ['name' => 'baz', 'title' => 'quux', 'except' => $except],
        'quuz'   => ['name' => 'corge', 'title' => 'grault', 'except' => $except],
        'garply' => ['name' => 'waldo', 'title' => 'plugh', 'except' => $except],
        'xyzzy'  => ['name' => 'thud', 'title' => 'wooo', 'except' => $except],
    ];

    if ($uppercase) {
        $dataset = array_map(function (array $element) {
            $element['name'] = strtoupper($element['name']);

            return $element;
        }, $dataset);
    }

    shuffle($dataset);

    return array_slice($dataset, 0, $count);
});
