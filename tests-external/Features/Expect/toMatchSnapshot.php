<?php

use Pest\TestSuite;

beforeEach(function () {
    $this->snapshotable = <<<'HTML'
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h1>Snapshot</h1>
                </div>
            </div>
        </div>
    HTML;
});

test('pass with dataset', function ($data) {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);
    [$filename] = TestSuite::getInstance()->snapshots->get();

    expect($filename)->toStartWith('tests/.pest/snapshots-external/')
        ->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value___.snap')
        ->and($this->snapshotable)->toMatchSnapshot();
})->with(['my-datas-set-value']);

describe('within describe', function () {
    test('pass with dataset', function ($data) {
        TestSuite::getInstance()->snapshots->save($this->snapshotable);
        [$filename] = TestSuite::getInstance()->snapshots->get();

        expect($filename)->toStartWith('tests/.pest/snapshots-external/')
            ->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value___.snap')
            ->and($this->snapshotable)->toMatchSnapshot();
    });
})->with(['my-datas-set-value']);
