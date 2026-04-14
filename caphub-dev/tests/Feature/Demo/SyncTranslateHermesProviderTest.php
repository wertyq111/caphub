<?php

use App\Clients\Ai\OpenClaw\OpenClawClient;
use App\Clients\Ai\Hermes\HermesClient;
use App\Models\AiInvocation;
use App\Models\SystemSetting;
use App\Models\TranslationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('uses the hermes provider for sync translation when the admin setting selects hermes', function () {
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
                'provider_model' => 'gpt-5-mini',
            ],
        ]);

    app()->instance(HermesClient::class, $mockClient);

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
        ->assertJsonPath('meta.provider_model', 'gpt-5-mini');

    $this->assertDatabaseHas('ai_invocations', [
        'agent_name' => 'chemical-news-translator',
        'status' => 'success',
    ]);
});

it('routes very short sync text through the configured short text provider', function () {
    Cache::flush();

    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 45,
    ]);

    config()->set('services.hermes', [
        'base_url' => 'http://127.0.0.1:8643/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 120,
    ]);

    config()->set('services.translation.sync_short_text_provider', 'hermes');
    config()->set('services.translation.sync_short_text_max_length', 4);

    SystemSetting::query()->create([
        'key' => 'translation.active_provider',
        'value' => 'openclaw',
    ]);

    $openClawClient = Mockery::mock(OpenClawClient::class);
    $openClawClient->shouldNotReceive('sendTranslationPayload');
    app()->instance(OpenClawClient::class, $openClawClient);

    $hermesClient = Mockery::mock(HermesClient::class);
    $hermesClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->withArgs(function (array $payload, bool $enforceTargetLanguage, bool $allowPartialTranslatedDocument): bool {
            return data_get($payload, 'input_document.text') === '乙烯'
                && $enforceTargetLanguage === true
                && $allowPartialTranslatedDocument === false;
        })
        ->andReturn([
            'translated_document' => [
                'text' => 'ethylene',
            ],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'gpt-5-mini',
            ],
        ]);

    app()->instance(HermesClient::class, $hermesClient);

    $response = $this->postJson('/api/demo/translate/sync', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯',
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'ethylene')
        ->assertJsonPath('meta.provider_model', 'gpt-5-mini');

    $job = TranslationJob::query()->firstOrFail();
    $invocation = AiInvocation::query()->firstOrFail();

    expect($job->status)->toBe(\App\Enums\TranslationJobStatus::Succeeded);
    expect($invocation->job_id)->toBe($job->id);
    expect(data_get($invocation->request_payload, 'execution_context.provider'))->toBe('hermes');
});
