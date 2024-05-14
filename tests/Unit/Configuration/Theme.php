<?php

it('creates a theme instance', function () {
    $theme = pest()->theme();

    expect($theme)->toBeInstanceOf(Pest\Configuration\Theme::class);
});
