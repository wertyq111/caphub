<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use App\Enums\TranslationProvider;
use App\Models\Glossary;
use App\Models\TranslationJob;
use App\Services\Translation\TranslationProviderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('caches identical sync translation requests and persists the first response', function () {
    Cache::flush();

    config()->set('services.github_models', [
        'base_url' => 'https://models.github.ai/inference',
        'api_key' => 'github-models-test-key',
        'model' => 'openai/gpt-5-mini',
        'timeout' => 45,
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

    $mockClient = Mockery::mock(GitHubModelsClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->andReturn([
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
        ]);

    app()->instance(GitHubModelsClient::class, $mockClient);

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

it('reuses the same sync cache entry for short text even after switching the long-text provider', function () {
    Cache::flush();

    config()->set('services.hermes', [
        'base_url' => 'http://127.0.0.1:8643/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 120,
    ]);

    config()->set('services.github_models', [
        'base_url' => 'https://models.github.ai/inference',
        'api_key' => 'github-models-test-key',
        'model' => 'openai/gpt-5-mini',
        'timeout' => 45,
    ]);

    $mockClient = Mockery::mock(GitHubModelsClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->andReturn([
            'translated_document' => [
                'text' => 'GitHub Models translation.',
            ],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'openai/gpt-5-mini',
            ],
        ]);

    app()->instance(GitHubModelsClient::class, $mockClient);

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
        ->assertJsonPath('translated_document.text', 'GitHub Models translation.')
        ->assertJsonPath('meta.cache_hit', false);

    app(TranslationProviderSettings::class)->setCurrent(TranslationProvider::Hermes);

    $hermesResponse = $this->postJson('/api/demo/translate/sync', $payload);

    $hermesResponse
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'GitHub Models translation.')
        ->assertJsonPath('meta.cache_hit', true)
        ->assertJsonPath('meta.provider_model', 'openai/gpt-5-mini');

});
