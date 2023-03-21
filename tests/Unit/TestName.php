<?php

it('may start with P', function (string $real, string $toBePrinted) {
    $printed = preg_replace('/P\\\/', '', $real, 1);

    expect($printed)->toBe($toBePrinted);
})->with([
    ['P\Tests\BarTest', 'Tests\BarTest'],
    ['P\Packages\Foo', 'Packages\Foo'],
    ['P\PPPackages\Foo', 'PPPackages\Foo'],
    ['PPPackages\Foo', 'PPPackages\Foo'],
    ['PPPackages\Foo', 'PPPackages\Foo'],
]);

$names = [
    'ふが' => '__pest_evaluable_ふが',
    'ほげ' => 'ほげ',
    '卜竹弓一十山' => '卜竹弓一十山',
    '!p8VrB' => '!p8VrB',
    '&xe6VeKWF#n4' => '&xe6VeKWF#n4',
    '%%HurHUnw7zM!' => '%%HurHUnw7zM!',
    'rundeliekend' => 'rundeliekend',
    'g%%c!Jt9$fy#Kf' => 'g%%c!Jt9$fy#Kf',
    'NRs*Gz2@hmB$W$BPD%%b2U%3P%z%apnwSX' => 'NRs*Gz2@hmB$W$BPD%%b2U%3P%z%apnwSX',
];

foreach ($names as $name => $methodName) {
    test($name)
        ->expect(fn () => static::getLatestPrintableTestCaseMethodName())
        ->toBe($name)
        ->and(fn () => $this->name())
        ->toBe($methodName);
}
