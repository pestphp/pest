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
    'test description' => '__pest_evaluable_test_description',
    'test_description' => '__pest_evaluable_test__description',
    'ふ+が+' => '__pest_evaluable_ふ_が_',
    'ほげ' => '__pest_evaluable_ほげ',
    '卜竹弓一十山' => '__pest_evaluable_卜竹弓一十山',
    'アゴデヸ' => '__pest_evaluable_アゴデヸ',
    '!p8VrB' => '__pest_evaluable__p8VrB',
    '&amp;xe6VeKWF#n4' => '__pest_evaluable__amp_xe6VeKWF_n4',
    '%%HurHUnw7zM!' => '__pest_evaluable___HurHUnw7zM_',
    'rundeliekend' => '__pest_evaluable_rundeliekend',
    'g%%c!Jt9$fy#Kf' => '__pest_evaluable_g__c_Jt9_fy_Kf',
    'NRs*Gz2@hmB$W$BPD%%b2U%3P%z%apnwSX' => '__pest_evaluable_NRs_Gz2_hmB_W_BPD__b2U_3P_z_apnwSX',
    'ÀÄ¤{¼÷' => '__pest_evaluable_ÀÄ¤_¼÷',
    'ìèéàòç' => '__pest_evaluable_ìèéàòç',
    'زهراء المعادي' => '__pest_evaluable_زهراء_المعادي',
    'الجبيهه' => '__pest_evaluable_الجبيهه',
    'الظهران' => '__pest_evaluable_الظهران',
    'Каролин' => '__pest_evaluable_Каролин',
    'অ্যান্টার্কটিকা' => '__pest_evaluable_অ্যান্টার্কটিকা',
    'Frýdek-Místek"' => '__pest_evaluable_Frýdek_Místek_',
    'Allingåbro&amp;' => '__pest_evaluable_Allingåbro_amp_',
    'Κεντροαφρικανική Δημοκρατία' => '__pest_evaluable_Κεντροαφρικανική_Δημοκρατία',
    'آذربایجان غربی' => '__pest_evaluable_آذربایجان_غربی',
    'זימבבואה' => '__pest_evaluable_זימבבואה',
    'Belišće' => '__pest_evaluable_Belišće',
    'Գվատեմալա' => '__pest_evaluable_Գվատեմալա',
    'パプアニューギニア' => '__pest_evaluable_パプアニューギニア',
    '富山県' => '__pest_evaluable_富山県',
    'Қарағанды' => '__pest_evaluable_Қарағанды',
    'Қостанай' => '__pest_evaluable_Қостанай',
    '안양시 동안구' => '__pest_evaluable_안양시_동안구',
    'Itālija' => '__pest_evaluable_Itālija',
    'Honningsvåg' => '__pest_evaluable_Honningsvåg',
    'Águeda' => '__pest_evaluable_Águeda',
    'Râșcani' => '__pest_evaluable_Râșcani',
    'Năsăud' => '__pest_evaluable_Năsăud',
    'Орехово-Зуево' => '__pest_evaluable_Орехово_Зуево',
    'Čereňany' => '__pest_evaluable_Čereňany',
    'Moravče' => '__pest_evaluable_Moravče',
    'Šentjernej' => '__pest_evaluable_Šentjernej',
    'Врање' => '__pest_evaluable_Врање',
    'Крушевац' => '__pest_evaluable_Крушевац',
    'Åkersberga' => '__pest_evaluable_Åkersberga',
    'บอสเนียและเฮอร์เซโกวีนา' => '__pest_evaluable_บอสเนียและเฮอร์เซโกวีนา',
    'Birleşik Arap Emirlikleri' => '__pest_evaluable_Birleşik_Arap_Emirlikleri',
    'Німеччина' => '__pest_evaluable_Німеччина',
    'Nam Định' => '__pest_evaluable_Nam_Định',
    '呼和浩特' => '__pest_evaluable_呼和浩特',
    'test /** with comment */ should do' => '__pest_evaluable_test_____with_comment____should_do',
];

foreach ($names as $name => $methodName) {
    test($name)
        ->expect(fn () => static::getLatestPrintableTestCaseMethodName())
        ->toBe($name)
        ->and(fn () => $this->name())
        ->toBe($methodName);
}
