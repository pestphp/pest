<?php

it('has {name} page', function () {
    $response = $this->get('/{name}');

    $response->assertStatus(200);
});
