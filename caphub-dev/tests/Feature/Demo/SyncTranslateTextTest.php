<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns a translated text document for the sync translation endpoint', function () {
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

    $response = $this->postJson('/api/demo/translate/sync', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'Ethylene prices rose.')
        ->assertJsonPath('meta.schema_version', 'v1');

    $this->assertDatabaseHas('ai_invocations', [
        'agent_name' => 'chemical-news-translator',
        'status' => 'success',
    ]);
});

it('rejects mismatched content for plain text input', function () {
    $response = $this->postJson('/api/demo/translate/sync', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'title' => '乙烯价格上涨',
        ],
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content.text', 'content.title']);
});

it('returns a stable json error when the upstream translation call fails', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    Http::fake(fn () => throw new RuntimeException('OpenClaw unavailable'));

    $response = $this->postJson('/api/demo/translate/sync', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ]);

    $response
        ->assertStatus(502)
        ->assertExactJson([
            'message' => 'Translation failed.',
        ]);
});

it('records sync job timing around the upstream translation call', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    $startedAt = Carbon::parse('2026-04-14 10:00:00');
    $finishedAt = $startedAt->copy()->addSeconds(12);

    Carbon::setTestNow($startedAt);

    Http::fake([
        '*' => function () use ($finishedAt) {
            Carbon::setTestNow($finishedAt);

            return Http::response([
                'translated_document' => [
                    'text' => 'Recorded with real timing.',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                ],
            ], 200);
        },
    ]);

    try {
        $this->postJson('/api/demo/translate/sync', [
            'input_type' => 'plain_text',
            'source_lang' => 'zh-CN',
            'target_lang' => 'en',
            'content' => [
                'text' => '中文翻译',
            ],
        ])->assertOk();
    } finally {
        Carbon::setTestNow();
    }

    $job = \App\Models\TranslationJob::query()->firstOrFail();

    expect($job->started_at?->equalTo($startedAt))->toBeTrue();
    expect($job->finished_at?->equalTo($finishedAt))->toBeTrue();
});

it('rejects translated content that still contains chinese when target language is english', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    Http::fake([
        '*' => Http::response([
            'translated_document' => [
                'text' => 'Ethylene prices 价格 rose.',
            ],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/sync', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ]);

    $response
        ->assertStatus(502)
        ->assertExactJson([
            'message' => 'Translation failed.',
        ]);
});
