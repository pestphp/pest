<?php

it('uses the right trait by using Symfony Finder call', function () {
    expect(class_uses($this))->toContain(TestTraitUsedByFinder::class);
});

it('uses the additional trait from the Symfony Finder call', function () {
    expect(class_uses($this))->toContain(MultipleTestTraitUsedByFinder::class);
});
