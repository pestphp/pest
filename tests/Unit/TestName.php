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
    'ほげ' => '__pest_evaluable_ほげ',
    '卜竹弓一十山' => '__pest_evaluable_卜竹弓一十山',
    '!p8VrB' => '__pest_evaluable__p8VrB',
    '&xe6VeKWF#n4' => '__pest_evaluable__xe6VeKWF_n4',
    '%%HurHUnw7zM!' => '__pest_evaluable___HurHUnw7zM_',
    'rundeliekend' => '__pest_evaluable_rundeliekend',
    'g%%c!Jt9$fy#Kf' => '__pest_evaluable_g__c_Jt9_fy_Kf',
    'NRs*Gz2@hmB$W$BPD%%b2U%3P%z%apnwSX' => '__pest_evaluable_NRs_Gz2_hmB_W_BPD__b2U_3P_z_apnwSX',
    'ÀÄ¤{¼' => '__pest_evaluable_ÀÄ¤_¼',
];

foreach ($names as $name => $methodName) {
    test($name)
        ->expect(fn () => static::getLatestPrintableTestCaseMethodName())
        ->toBe($name)
        ->and(fn () => $this->name())
        ->toBe($methodName);
}
