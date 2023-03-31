<?php

use NunoMaduro\Collision\Contracts\RenderableOnCollisionEditor;
use PHPUnit\Event\Code\ThrowableBuilder;
use Whoops\Exception\Frame;

test('collision editor can be added to the stack trace', function () {
    $exception = new class extends Exception implements RenderableOnCollisionEditor
    {
        public function __construct()
        {
            parent::__construct('test exception');
        }

        public function toCollisionEditor(): Frame
        {
            return new Frame([
                'file' => __DIR__.'/../../Pest.php',
                'line' => 5,
            ]);
        }
    };

    expect(ThrowableBuilder::from($exception))
        ->stackTrace()->toStartWith('/data/projects/open-source/pest/tests/Unit/Overrides/../../Pest.php:5')
        ->and(ThrowableBuilder::from(new Exception('test')))
        ->stackTrace()->toStartWith('/data/projects/open-source/pest/tests/Unit/Overrides/ThrowableBuilder.php:26');
});
