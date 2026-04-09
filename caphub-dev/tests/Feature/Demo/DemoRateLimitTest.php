<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('rate limits the demo sync translation endpoint per ip', function () {
    Cache::flush();

    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    Http::fake([
        '*' => Http::response([
            'translated_document' => [
                'text' => 'Ethylene prices rose.',
            ],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
            ],
        ], 200),
    ]);

    $response = null;

    for ($i = 1; $i <= 11; $i++) {
        $response = $this->postJson('/api/demo/translate/sync', [
            'input_type' => 'plain_text',
            'source_lang' => 'zh',
            'target_lang' => 'en',
            'content' => [
                'text' => "乙烯价格上涨 {$i}。",
            ],
        ]);
    }

    $response->assertTooManyRequests();

    Http::assertSentCount(10);
});
