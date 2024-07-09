<?php

use Pest\TestSuite;
use PHPUnit\Framework\ExpectationFailedException;

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

test('pass', function () {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    expect($this->snapshotable)->toMatchSnapshot();
});

expect()->pipe('toMatchSnapshot', function (Closure $next) {
    if (is_string($this->value)) {
        $this->value = preg_replace(
            '/name="_token" value=".*"/',
            'name="_token" value="1"',
            $this->value
        );
    }

    return $next();
});

test('pass using pipes', function () {
    expect('<input type="hidden" name="_token" value="'.random_int(1, 999).'" />')
        ->toMatchSnapshot();
});

test('pass with `__toString`', function () {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function __toString()
        {
            return $this->snapshotable;
        }
    };

    expect($object)->toMatchSnapshot();
});

test('pass with `toString`', function () {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function toString()
        {
            return $this->snapshotable;
        }
    };

    expect($object)->toMatchSnapshot();
});

test('pass with dataset', function ($data) {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);
    [$filename] = TestSuite::getInstance()->snapshots->get();

    expect($filename)->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value______my_datas_set_value__.snap')
        ->and($this->snapshotable)->toMatchSnapshot();
})->with(['my-datas-set-value']);

describe('within describe', function () {
    test('pass with dataset', function ($data) {
        TestSuite::getInstance()->snapshots->save($this->snapshotable);
        [$filename] = TestSuite::getInstance()->snapshots->get();

        expect($filename)->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value______my_datas_set_value__.snap')
            ->and($this->snapshotable)->toMatchSnapshot();
    });
})->with(['my-datas-set-value']);

test('pass with `toArray`', function () {
    TestSuite::getInstance()->snapshots->save(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function toArray()
        {
            return [
                'key' => $this->snapshotable,
            ];
        }
    };

    expect($object)->toMatchSnapshot();
});

test('pass with array', function () {
    TestSuite::getInstance()->snapshots->save(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

    expect([
        'key' => $this->snapshotable,
    ])->toMatchSnapshot();
});

test('pass with `toSnapshot`', function () {
    TestSuite::getInstance()->snapshots->save(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function toSnapshot()
        {
            return json_encode([
                'key' => $this->snapshotable,
            ], JSON_PRETTY_PRINT);
        }
    };

    expect($object)->toMatchSnapshot();
});

test('failures', function () {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    expect('contain that does not match snapshot')->toMatchSnapshot();
})->throws(ExpectationFailedException::class, 'Failed asserting that two strings are identical.');

test('failures with custom message', function () {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    expect('contain that does not match snapshot')->toMatchSnapshot('oh no');
})->throws(ExpectationFailedException::class, 'oh no');

test('not failures', function () {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    expect($this->snapshotable)->not->toMatchSnapshot();
})->throws(ExpectationFailedException::class);

test('multiple snapshot expectations', function () {
    expect('foo bar 1')->toMatchSnapshot();

    expect('foo bar 2')->toMatchSnapshot();
});

test('multiple snapshot expectations with datasets', function () {
    expect('foo bar 1')->toMatchSnapshot();

    expect('foo bar 2')->toMatchSnapshot();
})->with([1, 'foo', 'bar', 'baz']);

describe('describable', function () {
    test('multiple snapshot expectations with describe', function () {
        expect('foo bar 1')->toMatchSnapshot();

        expect('foo bar 2')->toMatchSnapshot();
    });
});

test('multiple snapshot expectations with repeat', function () {
    expect('foo bar 1')->toMatchSnapshot();

    expect('foo bar 2')->toMatchSnapshot();
})->repeat(10);
