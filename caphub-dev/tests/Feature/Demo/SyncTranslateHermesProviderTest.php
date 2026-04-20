<?php

use App\Clients\Ai\Hermes\HermesClient;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('uses the hermes provider for sync long plain text when the admin setting selects hermes', function () {
    Cache::flush();

    config()->set('services.hermes', [
        'base_url' => 'http://127.0.0.1:8643/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 120,
    ]);

    SystemSetting::query()->create([
        'key' => 'translation.active_provider',
        'value' => 'hermes',
    ]);

    $mockClient = Mockery::mock(HermesClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->withArgs(function (array $payload, bool $enforceTargetLanguage, bool $allowPartialTranslatedDocument): bool {
            return $payload['task_type'] === 'translation'
                && mb_strlen((string) data_get($payload, 'input_document.text')) > 1800
                && $enforceTargetLanguage === true
                && $allowPartialTranslatedDocument === false;
        })
        ->andReturn([
            'translated_document' => [
                'text' => 'Ethylene prices rose.',
            ],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'gpt-5-mini',
            ],
        ]);

    app()->instance(HermesClient::class, $mockClient);

    $response = $this->postJson('/api/demo/translate/sync', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => str_repeat('乙烯价格上涨。', 400),
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'Ethylene prices rose.')
        ->assertJsonPath('meta.provider_model', 'gpt-5-mini');

    $this->assertDatabaseHas('ai_invocations', [
        'agent_name' => 'chemical-news-translator',
        'status' => 'succeeded',
    ]);
});
