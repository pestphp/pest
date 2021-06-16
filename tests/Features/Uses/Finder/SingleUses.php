<?php

it('uses the right trait by using Symfony Finder call', function () {
    expect(class_uses($this))->toContain(TestTraitUsedByFinder::class);
});

it('does not use the additional trait from the Symfony Finder call', function () {
    expect(class_uses($this))->not->toContain(MultipleTestTraitUsedByFinder::class);
});
