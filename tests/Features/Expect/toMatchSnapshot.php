<?php

use Pest\TestSuite;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
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

test('pass', function (): void {
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

test('pass using pipes', function (): void {
    expect('<input type="hidden" name="_token" value="'.random_int(1, 999).'" />')
        ->toMatchSnapshot();
});

test('pass with `__toString`', function (): void {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function __toString(): string
        {
            return $this->snapshotable;
        }
    };

    expect($object)->toMatchSnapshot();
});

test('pass with `toString`', function (): void {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function toString(): string
        {
            return $this->snapshotable;
        }
    };

    expect($object)->toMatchSnapshot();
});

test('pass with dataset', function ($data): void {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);
    [$filename] = TestSuite::getInstance()->snapshots->get();

    expect($filename)->toStartWith('tests/.pest/snapshots/')
        ->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value___.snap')
        ->and($this->snapshotable)->toMatchSnapshot();
})->with(['my-datas-set-value']);

describe('within describe', function (): void {
    test('pass with dataset', function ($data): void {
        TestSuite::getInstance()->snapshots->save($this->snapshotable);
        [$filename] = TestSuite::getInstance()->snapshots->get();

        expect($filename)->toStartWith('tests/.pest/snapshots/')
            ->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value___.snap')
            ->and($this->snapshotable)->toMatchSnapshot();
    });
})->with(['my-datas-set-value']);

test('pass with `toArray`', function (): void {
    TestSuite::getInstance()->snapshots->save(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function toArray(): array
        {
            return [
                'key' => $this->snapshotable,
            ];
        }
    };

    expect($object)->toMatchSnapshot();
});

test('pass with array', function (): void {
    TestSuite::getInstance()->snapshots->save(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

    expect([
        'key' => $this->snapshotable,
    ])->toMatchSnapshot();
});

test('pass with `toSnapshot`', function (): void {
    TestSuite::getInstance()->snapshots->save(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

    $object = new class($this->snapshotable)
    {
        public function __construct(protected string $snapshotable) {}

        public function toSnapshot(): string|false
        {
            return json_encode([
                'key' => $this->snapshotable,
            ], JSON_PRETTY_PRINT);
        }
    };

    expect($object)->toMatchSnapshot();
});

test('not failures', function (): void {
    TestSuite::getInstance()->snapshots->save($this->snapshotable);

    expect($this->snapshotable)->not->toMatchSnapshot();
})->throws(ExpectationFailedException::class);

test('multiple snapshot expectations', function (): void {
    expect('foo bar 1')->toMatchSnapshot()
        ->and('foo bar 2')->toMatchSnapshot();
});

test('multiple snapshot expectations with datasets', function (): void {
    expect('foo bar 1')->toMatchSnapshot()
        ->and('foo bar 2')->toMatchSnapshot();
})->with([1, 'foo', 'bar', 'baz']);

describe('describable', function (): void {
    test('multiple snapshot expectations with describe', function (): void {
        expect('foo bar 1')->toMatchSnapshot()
            ->and('foo bar 2')->toMatchSnapshot();
    });
});

test('multiple snapshot expectations with repeat', function (): void {
    expect('foo bar 1')->toMatchSnapshot()
        ->and('foo bar 2')->toMatchSnapshot();
})->repeat(10);
