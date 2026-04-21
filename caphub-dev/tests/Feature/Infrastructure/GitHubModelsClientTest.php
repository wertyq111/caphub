<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use App\Clients\Ai\GitHubModels\GitHubModelsTranslationGateway;
use App\Clients\Ai\OpenClaw\AiInvocationLogger;
use App\Clients\Ai\OpenClaw\TranslationAgentPayloadBuilder;
use App\Models\AiInvocation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('dispatches github models translation through the copilot chat completions api', function () {
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl-test',
            'model' => 'gpt-4o',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
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
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    $response = app(GitHubModelsClient::class)->sendTranslationPayload([
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

    Http::assertSent(function (Request $request) {
        $payload = $request->data();

        expect($request->url())->toBe('https://api.githubcopilot.com/chat/completions');
        expect($request->hasHeader('Authorization', 'Bearer copilot-api-test-key'))->toBeTrue();
        expect($payload['model'])->toBe('gpt-4o');
        expect($payload['stream'])->toBeFalse();

        return true;
    });

    expect(data_get($response, 'translated_document.text'))->toBe('Ethylene prices rose.');
    expect(data_get($response, 'meta.provider_model'))->toBe('gpt-4o');
    expect(data_get($response, 'meta.retry_count'))->toBe(0);
    expect(data_get($response, 'meta.upstream_http_status'))->toBe(200);
});

it('dispatches github models concurrent translation requests through the http pool client', function () {
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'id' => 'chatcmpl-first',
                'model' => 'gpt-4o',
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'translated_document' => [
                                'segment_0' => 'First segment',
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
            ], 200)
            ->push([
                'id' => 'chatcmpl-second',
                'model' => 'gpt-4o',
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'translated_document' => [
                                'segment_1' => 'Second segment',
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

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request) {
        expect($request->url())->toBe('https://api.githubcopilot.com/chat/completions');
        expect(data_get($request->data(), 'model'))->toBe('gpt-4o');

        return true;
    });

    expect(data_get($results, '0.response.translated_document.segment_0'))->toBe('First segment');
    expect(data_get($results, '1.response.translated_document.segment_1'))->toBe('Second segment');
    expect(data_get($results, '0.response.meta.retry_count'))->toBe(0);
    expect(data_get($results, '1.response.meta.retry_count'))->toBe(0);
});

it('retries github models single translation requests after 429 responses', function () {
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
        'retry_times' => 1,
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'error' => [
                    'message' => 'rate limit',
                ],
            ], 429, [
                'Retry-After' => '0',
            ])
            ->push([
                'id' => 'chatcmpl-retry',
                'model' => 'gpt-4o',
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
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
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $response = app(GitHubModelsClient::class)->sendTranslationPayload([
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

    Http::assertSentCount(2);
    expect(data_get($response, 'translated_document.text'))->toBe('Ethylene prices rose.');
    expect(data_get($response, 'meta.retry_count'))->toBe(1);
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
                        'provider_model' => 'gpt-4o',
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
                        'provider_model' => 'gpt-4o',
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
    ], 2, false, true);

    expect(data_get($results, '0.response.translated_document.segment_0'))->toBe('First segment');
    expect(data_get($results, '1.response.translated_document.segment_1'))->toBe('Second segment');
    expect(data_get($results, '0.response.meta.provider_dispatch_mode'))->toBe('bounded_concurrent');
    expect(data_get($results, '1.response.meta.provider_dispatch_mode'))->toBe('bounded_concurrent');
});
