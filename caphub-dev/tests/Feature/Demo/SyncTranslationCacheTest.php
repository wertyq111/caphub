<?php

use App\Enums\TranslationProvider;
use App\Models\Glossary;
use App\Models\TranslationJob;
use App\Services\Translation\TranslationProviderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('caches identical sync translation requests and persists the first response', function () {
    Cache::flush();

    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    $glossary = Glossary::query()->create([
        'term' => '乙烯',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'standard_translation' => 'ethylene',
        'domain' => 'chemical_news',
        'priority' => 10,
        'status' => 'active',
        'notes' => null,
    ]);

    Http::fake([
        '*' => Http::response([
            'translated_document' => [
                'text' => 'Ethylene prices rose.',
            ],
            'glossary_hits' => [
                [
                    'source_term' => '乙烯',
                    'chosen_translation' => 'ethylene',
                ],
            ],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
            ],
        ], 200),
    ]);

    $payload = [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ];

    $first = $this->postJson('/api/demo/translate/sync', $payload);

    $first
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'Ethylene prices rose.')
        ->assertJsonPath('meta.cache_hit', false);

    $second = $this->postJson('/api/demo/translate/sync', $payload);

    $second
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'Ethylene prices rose.')
        ->assertJsonPath('glossary_hits.0.source_term', '乙烯')
        ->assertJsonPath('meta.cache_hit', true);

    Http::assertSentCount(1);

    expect(TranslationJob::query()->count())->toBe(1);

    $job = TranslationJob::query()->firstOrFail();

    $this->assertDatabaseHas('translation_results', [
        'translation_job_id' => $job->id,
    ]);

    $this->assertDatabaseHas('translation_glossary_hits', [
        'job_id' => $job->id,
        'glossary_id' => $glossary->id,
        'source_term' => '乙烯',
        'chosen_translation' => 'ethylene',
    ]);
});

it('separates sync cache entries between openclaw and hermes providers', function () {
    Cache::flush();

    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    config()->set('services.hermes', [
        'base_url' => 'http://127.0.0.1:8643/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 120,
    ]);

    Http::fake([
        'https://openclaw.example.test/*' => Http::response([
            'translated_document' => [
                'text' => 'OpenClaw translation.',
            ],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
            ],
        ], 200),
        'http://127.0.0.1:8643/v1/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'translated_document' => [
                            'text' => 'Hermes translation.',
                        ],
                        'glossary_hits' => [],
                        'risk_flags' => [],
                        'notes' => [],
                        'meta' => [
                            'schema_version' => 'v1',
                            'provider_model' => 'gpt-5-mini',
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ]],
        ], 200),
    ]);

    $payload = [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ];

    $openClawResponse = $this->postJson('/api/demo/translate/sync', $payload);

    $openClawResponse
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'OpenClaw translation.')
        ->assertJsonPath('meta.cache_hit', false);

    app(TranslationProviderSettings::class)->setCurrent(TranslationProvider::Hermes);

    $hermesResponse = $this->postJson('/api/demo/translate/sync', $payload);

    $hermesResponse
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'Hermes translation.')
        ->assertJsonPath('meta.cache_hit', false)
        ->assertJsonPath('meta.provider_model', 'gpt-5-mini');

    Http::assertSentCount(2);
});
