<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('uses the github models provider for sync short text even when the admin setting selects hermes', function () {
    Cache::flush();

    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    SystemSetting::query()->create([
        'key' => 'translation.active_provider',
        'value' => 'hermes',
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
                'provider_model' => 'gpt-4o',
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
        ->assertJsonPath('meta.provider_model', 'gpt-4o');

    $this->assertDatabaseHas('ai_invocations', [
        'agent_name' => 'gpt-4o',
        'status' => 'succeeded',
    ]);
});
