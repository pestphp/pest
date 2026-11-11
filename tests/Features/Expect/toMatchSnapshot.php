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
    TestSuite::getInstance()->snapshots->current()->write($this->snapshotable);

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
    TestSuite::getInstance()->snapshots->current()->write($this->snapshotable);

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
    TestSuite::getInstance()->snapshots->current()->write($this->snapshotable);

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
    TestSuite::getInstance()->snapshots->current()->write($this->snapshotable);
    $filename = TestSuite::getInstance()->snapshots->current()->path();

    expect($filename)->toStartWith('tests/.pest/snapshots/')
        ->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value___.snap')
        ->and($this->snapshotable)->toMatchSnapshot();
})->with(['my-datas-set-value']);

describe('within describe', function (): void {
    test('pass with dataset', function ($data): void {
        TestSuite::getInstance()->snapshots->current()->write($this->snapshotable);
        $filename = TestSuite::getInstance()->snapshots->current()->path();

        expect($filename)->toStartWith('tests/.pest/snapshots/')
            ->toEndWith('pass_with_dataset_with_data_set____my_datas_set_value___.snap')
            ->and($this->snapshotable)->toMatchSnapshot();
    });
})->with(['my-datas-set-value']);

test('pass with `toArray`', function (): void {
    TestSuite::getInstance()->snapshots->current()->write(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

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
    TestSuite::getInstance()->snapshots->current()->write(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

    expect([
        'key' => $this->snapshotable,
    ])->toMatchSnapshot();
});

test('pass with `toSnapshot`', function (): void {
    TestSuite::getInstance()->snapshots->current()->write(json_encode(['key' => $this->snapshotable], JSON_PRETTY_PRINT));

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
    TestSuite::getInstance()->snapshots->current()->write($this->snapshotable);

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

test('pass with named snapshot', function (): void {
    $snapshots = TestSuite::getInstance()->snapshots;
    $snapshots->named('header')->write($this->snapshotable);

    expect($snapshots->named('header')->path())
        ->toStartWith('tests/.pest/snapshots/')
        ->toEndWith('pass_with_named_snapshot__header.snap')
        ->and($this->snapshotable)->toMatchSnapshot(as: 'header');
});

test('named snapshots do not depend on the order they are asserted in', function (): void {
    $snapshots = TestSuite::getInstance()->snapshots;
    $snapshots->named('first')->write('foo bar 1');
    $snapshots->named('second')->write('foo bar 2');

    expect('foo bar 2')->toMatchSnapshot(as: 'second')
        ->and('foo bar 1')->toMatchSnapshot(as: 'first');
});

test('named snapshots do not consume the ordinal of the unnamed ones', function (): void {
    $snapshots = TestSuite::getInstance()->snapshots;
    $snapshots->current()->write('foo bar 1');
    $snapshots->named('named')->write('foo bar 2');

    expect('foo bar 1')->toMatchSnapshot()
        ->and('foo bar 2')->toMatchSnapshot(as: 'named')
        ->and($snapshots->current()->path())->toEndWith('named_snapshots_do_not_consume_the_ordinal_of_the_unnamed_ones.snap');
});

test('named snapshots require a name', function (): void {
    TestSuite::getInstance()->snapshots->named('_');
})->throws(InvalidArgumentException::class, 'The snapshot name must contain at least one alphanumeric character.');

test('ordinal snapshots start over once forgotten', function (): void {
    $snapshots = TestSuite::getInstance()->snapshots;

    $first = $snapshots->next()->path();

    expect($snapshots->next()->path())->toEndWith('ordinal_snapshots_start_over_once_forgotten__2.snap');

    $snapshots->forget();

    expect($snapshots->next()->path())->toBe($first);
});

test('ordinal snapshots start over on every repetition', function (): void {
    $snapshots = TestSuite::getInstance()->snapshots;

    $first = $snapshots->next()->path();

    expect($first)->toContain('ordinal_snapshots_start_over_on_every_repetition')
        ->and($snapshots->next()->path())->toBe(str_replace('.snap', '__2.snap', $first));
})->repeat(3);

test('snapshots of a repeated test are recorded per repetition', function (int $iteration): void {
    $snapshots = TestSuite::getInstance()->snapshots;
    $snapshots->current()->write('foo bar');
    $snapshots->named('named')->write('foo bar');

    expect('foo bar')->toMatchSnapshot()
        ->and($snapshots->current()->path())->toEndWith("_with_data_set___{$iteration}__.snap")
        ->and('foo bar')->toMatchSnapshot(as: 'named')
        ->and($snapshots->named('named')->path())->toEndWith("_with_data_set___{$iteration}____named.snap");
})->repeat(3);
