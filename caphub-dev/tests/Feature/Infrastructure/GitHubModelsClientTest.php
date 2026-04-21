<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use App\Clients\Ai\GitHubModels\GitHubModelsTranslationGateway;
use App\Clients\Ai\OpenClaw\AiInvocationLogger;
use App\Clients\Ai\OpenClaw\TranslationAgentPayloadBuilder;
use App\Models\AiInvocation;

it('dispatches github models requests through the copilot cli command', function () {
    config()->set('services.github_models', [
        'model' => 'openai/gpt-5-mini',
        'copilot_bin' => '/usr/bin/copilot',
        'copilot_excluded_tools' => 'shell,write,read,url,memory',
        'timeout' => 45,
    ]);

    $client = Mockery::mock(GitHubModelsClient::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $client->shouldReceive('runCopilotCommand')
        ->once()
        ->withArgs(function (array $command): bool {
            expect($command[0])->toBe('/usr/bin/copilot');
            expect($command)->toContain('--model');
            expect($command)->toContain('gpt-5-mini');
            expect($command)->toContain('-s');
            expect($command)->toContain('--allow-all-tools');
            expect($command)->toContain('--disable-builtin-mcps');
            expect($command)->toContain('--excluded-tools=shell,write,read,url,memory');

            $promptIndex = array_search('-p', $command, true);
            expect($promptIndex)->not->toBeFalse();
            expect($command[$promptIndex + 1] ?? '')->toContain('"text":"乙烯价格上涨。"');
            expect($command[$promptIndex + 1] ?? '')->toContain('"provider_model":"openai/gpt-5-mini"');

            return true;
        })
        ->andReturn(json_encode([
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
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

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

    expect(data_get($response, 'translated_document.text'))->toBe('Ethylene prices rose.');
    expect(data_get($response, 'meta.provider_model'))->toBe('openai/gpt-5-mini');
    expect(data_get($response, 'meta.retry_count'))->toBe(0);
});

it('surfaces copilot cli failures for github models translation', function () {
    config()->set('services.github_models', [
        'model' => 'openai/gpt-5-mini',
        'copilot_bin' => '/usr/bin/copilot',
        'copilot_excluded_tools' => 'shell,write,read,url,memory',
        'timeout' => 45,
    ]);

    $client = Mockery::mock(GitHubModelsClient::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $client->shouldReceive('runCopilotCommand')
        ->once()
        ->andThrow(new RuntimeException('Authentication failed.'));

    expect(fn () => $client->sendTranslationPayload([
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

it('serializes github models concurrent translation through repeated copilot cli calls', function () {
    config()->set('services.github_models', [
        'model' => 'openai/gpt-5-mini',
        'copilot_bin' => '/usr/bin/copilot',
        'copilot_excluded_tools' => 'shell,write,read,url,memory',
        'timeout' => 45,
    ]);

    $client = Mockery::mock(GitHubModelsClient::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $client->shouldReceive('runCopilotCommand')
        ->twice()
        ->andReturn(
            json_encode([
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
            json_encode([
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
        );

    $results = $client->sendTranslationPayloadsConcurrently([
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
    ], 2, false, true);

    expect(data_get($results, '0.response.translated_document.segment_0'))->toBe('First segment');
    expect(data_get($results, '1.response.translated_document.segment_1'))->toBe('Second segment');
    expect(data_get($results, '0.response.meta.provider_dispatch_mode'))->toBe('bounded_concurrent');
    expect(data_get($results, '1.response.meta.provider_dispatch_mode'))->toBe('bounded_concurrent');
});
