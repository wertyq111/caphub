<?php

use App\Clients\Ai\OpenClaw\OpenClawClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('posts a translation payload to OpenClaw and returns the response structure', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl_test',
            'object' => 'chat.completion',
            'created' => 1_775_111_081,
            'model' => 'openclaw',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'translated_document' => [
                            'title' => 'Ethylene prices rose',
                            'summary' => 'Market prices increased',
                            'body' => 'Ethylene prices rose.',
                        ],
                        'glossary_hits' => [
                            ['source_term' => '乙烯', 'chosen_translation' => 'ethylene'],
                        ],
                        'risk_flags' => [],
                        'notes' => [],
                        'meta' => [
                            'schema_version' => 'v1',
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    $response = app(OpenClawClient::class)->translate([
        'title' => '乙烯价格上涨',
        'summary' => '市场价格走高',
        'body' => '乙烯价格上涨。',
        'source_lang' => 'zh',
        'target_lang' => 'en',
    ], [
        [
            'source_term' => '乙烯',
            'target_term' => 'ethylene',
        ],
    ]);

    Http::assertSentCount(1);

    Http::assertSent(function (Request $request) {
        $payload = json_decode($request->body(), true);

        expect($request->url())->toBe('https://openclaw.example.test/v1/chat/completions');
        expect($request->hasHeader('Authorization', 'Bearer test-api-key'))->toBeTrue();
        expect($payload['model'])->toBe('chemical-news-translator');
        expect($payload['stream'])->toBeFalse();
        expect(data_get($payload, 'messages.0.role'))->toBe('system');
        expect(data_get($payload, 'messages.1.role'))->toBe('user');

        $userPayload = json_decode((string) data_get($payload, 'messages.1.content', ''), true);

        expect($userPayload)->toMatchArray([
            'task_type' => 'translation',
            'task_subtype' => 'chemical_news',
            'input_document' => [
                'title' => '乙烯价格上涨',
                'summary' => '市场价格走高',
                'body' => '乙烯价格上涨。',
            ],
            'context' => [
                'source_lang' => 'zh',
                'target_lang' => 'en',
                'glossary_entries' => [
                    [
                        'source_term' => '乙烯',
                        'target_term' => 'ethylene',
                    ],
                ],
                'constraints' => [
                    'preserve_units' => true,
                    'preserve_entities' => true,
                ],
            ],
            'output_schema_version' => 'v1',
        ]);

        return true;
    });

    expect($response)->toMatchArray([
        'translated_document' => [
            'title' => 'Ethylene prices rose',
            'summary' => 'Market prices increased',
            'body' => 'Ethylene prices rose.',
        ],
        'meta' => [
            'schema_version' => 'v1',
            'provider_model' => 'openclaw',
        ],
    ]);
});

it('includes plain text field when building translation payload for text input', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl_test',
            'object' => 'chat.completion',
            'created' => 1_775_111_081,
            'model' => 'openclaw',
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
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    $response = app(OpenClawClient::class)->translate([
        'text' => '乙烯价格上涨。',
        'source_lang' => 'zh',
        'target_lang' => 'en',
    ]);

    Http::assertSent(function (Request $request) {
        $payload = json_decode($request->body(), true);
        $userPayload = json_decode((string) data_get($payload, 'messages.1.content', ''), true);

        expect(data_get($userPayload, 'input_document'))->toBe([
            'text' => '乙烯价格上涨。',
        ]);

        return true;
    });

    expect(data_get($response, 'translated_document.text'))->toBe('Ethylene prices rose.');
});

it('fails fast when OpenClaw returns a drifted success payload', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl_test',
            'object' => 'chat.completion',
            'created' => 1_775_111_081,
            'model' => 'openclaw',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'translated_document' => ['text' => 'Ethylene prices rose.'],
                        'glossary_hits' => [],
                        'risk_flags' => [],
                        'notes' => [],
                        'meta' => ['schema_version' => 'v1'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    expect(fn () => app(OpenClawClient::class)->translate([
        'title' => '乙烯价格上涨',
        'source_lang' => 'zh',
        'target_lang' => 'en',
    ]))->toThrow(RuntimeException::class, 'OpenClaw translated_document is missing required key [title].');
});

it('allows lenient translation payloads to omit some translated document keys', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
    ]);

    Http::fake([
        '*' => Http::response([
            'id' => 'chatcmpl_partial',
            'object' => 'chat.completion',
            'created' => 1_775_111_083,
            'model' => 'openclaw',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'translated_document' => [
                            'summary' => 'Market prices increased',
                        ],
                        'glossary_hits' => [],
                        'risk_flags' => [],
                        'notes' => [],
                        'meta' => [
                            'schema_version' => 'v1',
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    $response = app(OpenClawClient::class)->translateLenient([
        'title' => '乙烯价格上涨',
        'summary' => '市场价格走高',
        'source_lang' => 'zh',
        'target_lang' => 'en',
    ]);

    expect($response)->toMatchArray([
        'translated_document' => [
            'summary' => 'Market prices increased',
        ],
        'meta' => [
            'schema_version' => 'v1',
            'provider_model' => 'openclaw',
        ],
    ]);
});

it('retries once when OpenClaw returns invalid json before succeeding', function () {
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 30,
        'retry_times' => 1,
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'id' => 'chatcmpl_bad',
                'object' => 'chat.completion',
                'created' => 1_775_111_081,
                'model' => 'openclaw',
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '{"translated_document":{"text":"broken"}',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200)
            ->push([
                'id' => 'chatcmpl_good',
                'object' => 'chat.completion',
                'created' => 1_775_111_082,
                'model' => 'openclaw',
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
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $response = app(OpenClawClient::class)->translate([
        'text' => '乙烯价格上涨。',
        'source_lang' => 'zh',
        'target_lang' => 'en',
    ]);

    Http::assertSentCount(2);
    expect(data_get($response, 'translated_document.text'))->toBe('Ethylene prices rose.');
});
