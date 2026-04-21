<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns a translated text document for the sync translation endpoint', function () {
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    $mockClient = Mockery::mock(GitHubModelsClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->andReturn([
            'translated_document' => [
                'text' => 'Ethylene prices rose.',
            ],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
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
        ->assertJsonPath('meta.schema_version', 'v1');

    $job = \App\Models\TranslationJob::query()->firstOrFail();

    $this->assertDatabaseHas('ai_invocations', [
        'job_id' => $job->id,
        'agent_name' => 'gpt-4o',
        'status' => 'succeeded',
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
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    $mockClient = Mockery::mock(GitHubModelsClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->andThrow(new RuntimeException('GitHub Models unavailable'));

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
        ->assertStatus(502)
        ->assertExactJson([
            'message' => 'Translation failed.',
        ]);

    $job = \App\Models\TranslationJob::query()->firstOrFail();

    expect($job->status)->toBe(\App\Enums\TranslationJobStatus::Failed);

    $this->assertDatabaseHas('ai_invocations', [
        'job_id' => $job->id,
        'agent_name' => 'gpt-4o',
        'status' => 'failed',
    ]);
});

it('records sync job timing around the upstream translation call', function () {
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    $startedAt = Carbon::parse('2026-04-14 10:00:00');
    $finishedAt = $startedAt->copy()->addSeconds(12);

    Carbon::setTestNow($startedAt);

    $mockClient = Mockery::mock(GitHubModelsClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->andReturnUsing(function () use ($finishedAt) {
            Carbon::setTestNow($finishedAt);

            return [
                'translated_document' => [
                    'text' => 'Recorded with real timing.',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                ],
            ];
        });

    app()->instance(GitHubModelsClient::class, $mockClient);

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
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    Http::fake([
        'https://api.githubcopilot.com/chat/completions' => Http::response([
            'id' => 'chatcmpl-test',
            'model' => 'gpt-4o',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'translated_document' => [
                            'text' => 'Ethylene prices 价格 rose.',
                        ],
                        'glossary_hits' => [],
                        'risk_flags' => [],
                        'notes' => [],
                        'meta' => [
                            'schema_version' => 'v1',
                            'provider_model' => 'gpt-4o',
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                'finish_reason' => 'stop',
            ]],
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
