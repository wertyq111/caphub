<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use App\Clients\Ai\GitHubModels\GitHubModelsTranslationGateway;
use App\Clients\Ai\OpenClaw\AiInvocationLogger;
use App\Clients\Ai\OpenClaw\TranslationAgentPayloadBuilder;
use App\Models\AiInvocation;
use Illuminate\Support\Facades\Http;

it('dispatches github models requests through the copilot bridge', function () {
    config()->set('services.github_models', [
        'base_url' => 'http://host.docker.internal:18643',
        'api_key' => 'bridge-test-key',
        'model' => 'openai/gpt-5-mini',
        'timeout' => 45,
        'retry_times' => 2,
    ]);

    Http::fake([
        'http://host.docker.internal:18643/v1/completions' => Http::response([
            'content' => json_encode([
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
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'duration_ms' => 320,
            'exit_code' => 0,
            'model' => 'gpt-5-mini',
        ], 200),
    ]);

    $client = app(GitHubModelsClient::class);

    $response = $client->sendTranslationPayload([
        'task_type' => 'translation',
        'task_subtype' => 'chemical_news',
        'input_document' => [
            'text' => '乙烯价格上涨。',
        ],
        'context' => [
            'source_lang' => 'zh',
            'target_lang' => 'en',
            'glossary_entries' => [],
            'constraints' => [
                'preserve_units' => true,
                'preserve_entities' => true,
            ],
        ],
        'output_schema_version' => 'v1',
    ]);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        expect($request->url())->toBe('http://host.docker.internal:18643/v1/completions');
        expect($request->header('Authorization'))->toContain('Bearer bridge-test-key');
        expect($request['model'])->toBe('gpt-5-mini');
        expect($request['timeout'])->toBe(45);
        expect($request['prompt'])->toContain('"text":"乙烯价格上涨。"');
        expect($request['prompt'])->toContain('"provider_model":"openai/gpt-5-mini"');

        return true;
    });

    expect(data_get($response, 'translated_document.text'))->toBe('Ethylene prices rose.');
    expect(data_get($response, 'meta.provider_model'))->toBe('openai/gpt-5-mini');
    expect(data_get($response, 'meta.retry_count'))->toBe(0);
    expect(data_get($response, 'meta.bridge_duration_ms'))->toBe(320);
});

it('surfaces copilot bridge failures for github models translation', function () {
    config()->set('services.github_models', [
        'base_url' => 'http://host.docker.internal:18643',
        'api_key' => 'bridge-test-key',
        'model' => 'openai/gpt-5-mini',
        'timeout' => 45,
        'retry_times' => 0,
    ]);

    Http::fake([
        'http://host.docker.internal:18643/v1/completions' => Http::response([
            'message' => 'Authentication failed.',
        ], 502),
    ]);

    expect(fn () => app(GitHubModelsClient::class)->sendTranslationPayload([
        'task_type' => 'translation',
        'task_subtype' => 'chemical_news',
        'input_document' => [
            'text' => '乙烯价格上涨。',
        ],
        'context' => [
            'source_lang' => 'zh',
            'target_lang' => 'en',
            'glossary_entries' => [],
            'constraints' => [
                'preserve_units' => true,
                'preserve_entities' => true,
            ],
        ],
        'output_schema_version' => 'v1',
    ]))->toThrow(RuntimeException::class, 'Authentication failed.');
});

it('serializes github models concurrent translation through repeated copilot bridge calls', function () {
    config()->set('services.github_models', [
        'base_url' => 'http://host.docker.internal:18643',
        'api_key' => 'bridge-test-key',
        'model' => 'openai/gpt-5-mini',
        'timeout' => 45,
        'retry_times' => 0,
    ]);

    Http::fakeSequence()
        ->push([
            'content' => json_encode([
                'translated_document' => [
                    'segment_0' => 'First segment',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openai/gpt-5-mini',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'duration_ms' => 210,
            'exit_code' => 0,
            'model' => 'gpt-5-mini',
        ], 200)
        ->push([
            'content' => json_encode([
                'translated_document' => [
                    'segment_1' => 'Second segment',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openai/gpt-5-mini',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'duration_ms' => 220,
            'exit_code' => 0,
            'model' => 'gpt-5-mini',
        ], 200);

    $results = app(GitHubModelsClient::class)->sendTranslationPayloadsConcurrently([
        [
            'payload' => [
                'task_type' => 'translation',
                'task_subtype' => 'chemical_news',
                'input_document' => [
                    'segment_0' => '第一段',
                ],
                'context' => [
                    'source_lang' => 'zh',
                    'target_lang' => 'en',
                    'glossary_entries' => [],
                    'constraints' => [
                        'preserve_units' => true,
                        'preserve_entities' => true,
                    ],
                ],
                'output_schema_version' => 'v1',
            ],
        ],
        [
            'payload' => [
                'task_type' => 'translation',
                'task_subtype' => 'chemical_news',
                'input_document' => [
                    'segment_1' => '第二段',
                ],
                'context' => [
                    'source_lang' => 'zh',
                    'target_lang' => 'en',
                    'glossary_entries' => [],
                    'constraints' => [
                        'preserve_units' => true,
                        'preserve_entities' => true,
                    ],
                ],
                'output_schema_version' => 'v1',
            ],
        ],
    ], 2);

    expect(data_get($results, '0.response.translated_document.segment_0'))->toBe('First segment');
    expect(data_get($results, '1.response.translated_document.segment_1'))->toBe('Second segment');
    expect(data_get($results, '0.response.meta.retry_count'))->toBe(0);
    expect(data_get($results, '1.response.meta.retry_count'))->toBe(0);
    Http::assertSentCount(2);
});

it('routes github models concurrent translation through the batch client instead of serial single calls', function () {
    $client = Mockery::mock(GitHubModelsClient::class);
    $logger = Mockery::mock(AiInvocationLogger::class);

    $client->shouldNotReceive('sendTranslationPayload');
    $client->shouldReceive('sendTranslationPayloadsConcurrently')
        ->once()
        ->andReturn([
            0 => [
                'response' => [
                    'translated_document' => [
                        'segment_0' => 'First segment',
                    ],
                    'glossary_hits' => [],
                    'risk_flags' => [],
                    'notes' => [],
                    'meta' => [
                        'schema_version' => 'v1',
                        'provider_model' => 'openai/gpt-5-mini',
                        'retry_count' => 0,
                    ],
                ],
            ],
            1 => [
                'response' => [
                    'translated_document' => [
                        'segment_1' => 'Second segment',
                    ],
                    'glossary_hits' => [],
                    'risk_flags' => [],
                    'notes' => [],
                    'meta' => [
                        'schema_version' => 'v1',
                        'provider_model' => 'openai/gpt-5-mini',
                        'retry_count' => 0,
                    ],
                ],
            ],
        ]);

    $logger->shouldReceive('logTranslation')
        ->twice()
        ->andReturn(new AiInvocation());

    $gateway = new GitHubModelsTranslationGateway(
        $client,
        app(TranslationAgentPayloadBuilder::class),
        $logger,
    );

    $results = $gateway->translateDocumentsConcurrently([
        0 => [
            'payload' => [
                'task_type' => 'translation',
                'task_subtype' => 'chemical_news',
                'input_document' => [
                    'segment_0' => '第一段',
                ],
                'context' => [
                    'source_lang' => 'zh',
                    'target_lang' => 'en',
                    'glossary_entries' => [],
                    'constraints' => [
                        'preserve_units' => true,
                        'preserve_entities' => true,
                    ],
                ],
                'output_schema_version' => 'v1',
            ],
        ],
        1 => [
            'payload' => [
                'task_type' => 'translation',
                'task_subtype' => 'chemical_news',
                'input_document' => [
                    'segment_1' => '第二段',
                ],
                'context' => [
                    'source_lang' => 'zh',
                    'target_lang' => 'en',
                    'glossary_entries' => [],
                    'constraints' => [
                        'preserve_units' => true,
                        'preserve_entities' => true,
                    ],
                ],
                'output_schema_version' => 'v1',
            ],
        ],
    ], 2);

    expect(data_get($results, '0.response.translated_document.segment_0'))->toBe('First segment');
    expect(data_get($results, '1.response.translated_document.segment_1'))->toBe('Second segment');
});
