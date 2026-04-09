<?php

use Illuminate\Testing\TestResponse;

it('allows preflight requests from the admin frontend origin', function (): void {
    $response = $this->call('OPTIONS', '/api/admin/glossaries', [], [], [], [
        'HTTP_ORIGIN' => 'http://10.10.9.184:5188',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type',
    ]);

    expect($response)
        ->toBeInstanceOf(TestResponse::class)
        ->and($response->getStatusCode())->toBe(204)
        ->and($response->headers->get('Access-Control-Allow-Origin'))->toBe('http://10.10.9.184:5188');
});
