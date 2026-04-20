<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('uses the github models provider for sync translation when the admin setting selects github models', function () {
    Cache::flush();

    config()->set('services.github_models', [
        'base_url' => 'https://models.github.ai/inference',
        'api_key' => 'github-models-test-key',
        'model' => 'openai/gpt-5-mini',
        'timeout' => 45,
    ]);

    SystemSetting::query()->create([
        'key' => 'translation.active_provider',
        'value' => 'github_models',
    ]);

    $mockClient = Mockery::mock(GitHubModelsClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->withArgs(function (array $payload, bool $enforceTargetLanguage, bool $allowPartialTranslatedDocument): bool {
            return $payload['task_type'] === 'translation'
                && data_get($payload, 'input_document.text') === '乙烯价格上涨。'
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
                'provider_model' => 'openai/gpt-5-mini',
            ],
        ]);

    app()->instance(GitHubModelsClient::class, $mockClient);

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
        ->assertJsonPath('meta.provider_model', 'openai/gpt-5-mini');

    $this->assertDatabaseHas('ai_invocations', [
        'agent_name' => 'openai/gpt-5-mini',
        'status' => 'succeeded',
    ]);
});
