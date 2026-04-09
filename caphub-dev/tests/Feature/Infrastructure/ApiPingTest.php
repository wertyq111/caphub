<?php

it('returns a json ok response for the api ping endpoint', function () {
    $response = $this->getJson('/api/ping');

    $response->assertOk();
    $response->assertExactJson(['ok' => true]);
});
