<?php

dataset('with_arguments', function (int $count = 3, bool $uppercase = false) {
    $dataset = [
        'foo'    => ['name' => 'bar'],
        'baz'    => ['name' => 'baz', 'title' => 'quux'],
        'quuz'   => ['name' => 'corge', 'title' => 'grault'],
        'garply' => ['name' => 'waldo', 'title' => 'plugh'],
        'xyzzy'  => ['name' => 'thud'],
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
