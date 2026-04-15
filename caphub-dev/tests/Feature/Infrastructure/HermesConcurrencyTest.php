<?php

use App\Clients\Ai\Hermes\HermesClient;
use App\Clients\Ai\Hermes\HermesTranslationGateway;
use App\Clients\Ai\OpenClaw\AiInvocationLogger;
use App\Clients\Ai\OpenClaw\TranslationAgentPayloadBuilder;
use App\Models\AiInvocation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('dispatches Hermes concurrent translation requests through the HTTP pool client', function () {
    config()->set('services.hermes', [
        'base_url' => 'https://hermes.example.test/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 120,
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => [
                    'segment_0' => 'First segment',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'gpt-5-mini',
                ],
            ], 200)
            ->push([
                'translated_document' => [
                    'segment_1' => 'Second segment',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'gpt-5-mini',
                ],
            ], 200),
    ]);

    $results = app(HermesClient::class)->sendTranslationPayloadsConcurrently([
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
        expect($request->url())->toEndWith('chat/completions');
        expect($request->hasHeader('Authorization', 'Bearer hermes-test-key'))->toBeTrue();

        return true;
    });

    expect(data_get($results, '0.response.translated_document.segment_0'))->toBe('First segment');
    expect(data_get($results, '1.response.translated_document.segment_1'))->toBe('Second segment');
    expect(data_get($results, '0.response.meta.retry_count'))->toBe(0);
    expect(data_get($results, '1.response.meta.retry_count'))->toBe(0);
});

it('routes Hermes concurrent translation through the batch client instead of serial single calls', function () {
    $client = Mockery::mock(HermesClient::class);
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
                        'provider_model' => 'gpt-5-mini',
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
                        'provider_model' => 'gpt-5-mini',
                        'retry_count' => 0,
                    ],
                ],
            ],
        ]);

    $logger->shouldReceive('logTranslation')
        ->twice()
        ->andReturn(new AiInvocation());

    $gateway = new HermesTranslationGateway(
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
