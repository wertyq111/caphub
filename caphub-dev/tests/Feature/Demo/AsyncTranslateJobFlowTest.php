<?php

use App\Clients\Ai\GitHubModels\GitHubModelsClient;
use App\Services\TaskCenter\TranslationJobService;
use App\Services\Translation\AsyncTranslationResultHealthValidator;
use App\Services\Translation\GlossaryHitPersister;
use App\Services\Translation\HtmlTextNodeTranslator;
use App\Services\Translation\TranslationService;
use App\Enums\TranslationJobStatus;
use App\Jobs\FinalizeTranslationJob;
use App\Jobs\ProcessTranslationJob;
use App\Models\AiInvocation;
use App\Models\Glossary;
use App\Models\SystemSetting;
use App\Models\TranslationJob;
use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function fakeSemanticSegmentDocument(string $html, array $translationsBySegmentIndex): array
{
    /** @var HtmlTextNodeTranslator $translator */
    $translator = app(HtmlTextNodeTranslator::class);
    $compiled = $translator->compileSemanticSegments($html, 300, 600);
    $translatedDocument = [];

    foreach ($translationsBySegmentIndex as $segmentIndex => $nodeTexts) {
        $translatedDocument['segment_'.$segmentIndex] = $translator->encodeSegmentNodeTexts(
            $compiled['segments'][$segmentIndex],
            $nodeTexts,
        );
    }

    return $translatedDocument;
}

it('dispatches an async translation job and allows polling for status and result', function () {
    Bus::fake();
    config()->set('services.github_models', [
        'base_url' => 'https://api.githubcopilot.com',
        'api_key' => 'copilot-api-test-key',
        'model' => 'gpt-4o',
        'timeout' => 120,
    ]);

    SystemSetting::query()->updateOrCreate(
        ['key' => 'translation.active_provider'],
        ['value' => 'hermes'],
    );

    Glossary::query()->create([
        'term' => '乙烯',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'standard_translation' => 'ethylene',
        'domain' => 'chemical_news',
        'priority' => 10,
        'status' => 'active',
        'notes' => null,
    ]);

    $mockClient = Mockery::mock(GitHubModelsClient::class);
    $mockClient
        ->shouldReceive('sendTranslationPayload')
        ->once()
        ->andReturn([
            'translated_document' => [
                'text' => 'Ethylene prices rose.',
            ],
            'glossary_hits' => [
                [
                    'source_term' => '乙烯',
                    'chosen_translation' => 'ethylene',
                ],
            ],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'gpt-4o',
            ],
        ]);

    app()->instance(GitHubModelsClient::class, $mockClient);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ]);

    $response
        ->assertAccepted()
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure([
            'job_id',
            'job_uuid',
            'status',
        ]);

    Bus::assertDispatched(ProcessTranslationJob::class);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    $this->getJson("/api/demo/translate/jobs/{$job->job_uuid}")
        ->assertOk()
        ->assertJsonPath('job_uuid', $job->job_uuid)
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('source_document.text', '乙烯价格上涨。');

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) use ($job) {
        expect($finalizeJob->jobId)->toBe($job->id);
        expect($finalizeJob->result['mode'])->toBe('async');
        expect($finalizeJob->result['response']['translated_document']['text'])->toBe('Ethylene prices rose.');
        expect($finalizeJob->result['response']['glossary_hits'][0]['source_term'])->toBe('乙烯');

        return true;
    });

    $this->assertDatabaseHas('ai_invocations', [
        'job_id' => $job->id,
        'agent_name' => 'gpt-4o',
        'status' => 'succeeded',
    ]);

    (new FinalizeTranslationJob($job->id, [
        'mode' => 'async',
        'input_type' => 'plain_text',
        'response' => [
            'translated_document' => [
                'text' => 'Ethylene prices rose.',
            ],
            'glossary_hits' => [
                [
                    'source_term' => '乙烯',
                    'chosen_translation' => 'ethylene',
                ],
            ],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'gpt-4o',
            ],
        ],
    ]))->handle(
        app(TranslationJobService::class),
        app(AsyncTranslationResultHealthValidator::class),
        app(GlossaryHitPersister::class),
    );

    $this->getJson("/api/demo/translate/jobs/{$job->job_uuid}")
        ->assertOk()
        ->assertJsonPath('status', 'succeeded')
        ->assertJsonPath('source_document.text', '乙烯价格上涨。')
        ->assertJsonPath('translated_document.text', 'Ethylene prices rose.')
        ->assertJsonPath('translation_provider', 'github_models')
        ->assertJsonPath('translation_agent', 'gpt-4o');

    $this->getJson("/api/demo/translate/jobs/{$job->job_uuid}/result")
        ->assertOk()
        ->assertJsonPath('input_type', 'plain_text')
        ->assertJsonPath('glossary_hits.0.source_term', '乙烯')
        ->assertJsonPath('glossary_hits.0.chosen_translation', 'ethylene')
        ->assertJsonPath('translated_document.text', 'Ethylene prices rose.')
        ->assertJsonPath('meta.schema_version', 'v1');

    $this->assertDatabaseHas('translation_glossary_hits', [
        'job_id' => $job->id,
        'source_term' => '乙烯',
        'chosen_translation' => 'ethylene',
    ]);
});

it('chunks long async plain text translations and merges the translated text', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
    ]);

    $longText = implode("\n\n", [
        str_repeat('第一段乙烯价格上涨。', 120),
        str_repeat('第二段丙烯供应趋紧。', 120),
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => [
                    'text' => 'Chunk one translation.',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'chemical-news-translator',
                ],
            ], 200)
            ->push([
                'translated_document' => [
                    'text' => 'Chunk two translation.',
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'chemical-news-translator',
                ],
            ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $longText,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(2);

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) use ($job) {
        expect($finalizeJob->jobId)->toBe($job->id);
        expect($finalizeJob->result['response']['translated_document']['text'])->toBe("Chunk one translation.\n\nChunk two translation.");
        expect($finalizeJob->result['response']['meta']['chunked'])->toBeTrue();
        expect($finalizeJob->result['response']['meta']['chunk_count'])->toBe(2);

        return true;
    });
});

it('marks async html translations as failed when every text node falls back to the source content', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p><span style="color:#0000cd;">第一段内容</span><strong>第二段内容</strong></p>';
    $invalidSegmentDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
            1 => '第二段 content',
        ],
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => $invalidSegmentDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200)
            ->push([
                'translated_document' => $invalidSegmentDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(2);
    $finalizeJob = null;

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $dispatchedJob) use (&$finalizeJob) {
        $finalizeJob = $dispatchedJob;

        expect($finalizeJob->result['response']['translated_document']['text'])
            ->toBe('<p><span style="color:#0000cd;">第一段内容</span><strong>第二段内容</strong></p>');
        expect($finalizeJob->result['response']['meta']['html_mode'])->toBeTrue();
        expect($finalizeJob->result['response']['meta']['html_strategy'])->toBe('semantic_segment_parallel');
        expect($finalizeJob->result['response']['meta']['translated_text_nodes'])->toBe(0);
        expect($finalizeJob->result['response']['meta']['fallback_text_nodes'])->toBe(2);
        expect($finalizeJob->result['response']['meta']['html_fallback_segment_count'])->toBe(1);

        return true;
    });

    expect($finalizeJob)->toBeInstanceOf(FinalizeTranslationJob::class);

    $finalizeJob->handle(
        app(TranslationJobService::class),
        app(AsyncTranslationResultHealthValidator::class),
        app(GlossaryHitPersister::class),
    );

    $job->refresh()->load('result');

    expect($job->status)->toBe(TranslationJobStatus::Failed);
    expect($job->failure_reason)->toBe('full_fallback: translated output fell back to the source content for all available HTML text nodes.');
    expect($job->result)->not->toBeNull();
    expect($job->result->translated_document_json['text'])
        ->toBe('<p><span style="color:#0000cd;">第一段内容</span><strong>第二段内容</strong></p>');
    expect($job->result->meta_payload['full_fallback'])->toBeTrue();
    expect($job->result->meta_payload['fallback_ratio'])->toEqual(1.0);

    $this->getJson("/api/demo/translate/jobs/{$job->job_uuid}")
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error.reason', 'full_fallback: translated output fell back to the source content for all available HTML text nodes.');

    $this->getJson("/api/demo/translate/jobs/{$job->job_uuid}/result")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'translation_failed')
        ->assertJsonPath('error.reason', 'full_fallback: translated output fell back to the source content for all available HTML text nodes.');
});

it('keeps async html jobs successful when only part of the content falls back to the source', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p>第一段内容</p><p>第二段内容</p>';
    $partiallyInvalidDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
        ],
        1 => [
            0 => '第二段 content',
        ],
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => $partiallyInvalidDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200)
            ->push([
                'translated_document' => [
                    'segment_1' => fakeSemanticSegmentDocument('<p>第二段内容</p>', [
                        0 => [
                            0 => '第二段 content',
                        ],
                    ])['segment_0'],
                ],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    $finalizeJob = null;
    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $dispatchedJob) use (&$finalizeJob) {
        $finalizeJob = $dispatchedJob;

        return true;
    });

    expect($finalizeJob)->toBeInstanceOf(FinalizeTranslationJob::class);

    $finalizeJob->handle(
        app(TranslationJobService::class),
        app(AsyncTranslationResultHealthValidator::class),
        app(GlossaryHitPersister::class),
    );

    $job->refresh()->load('result');

    expect($job->status)->toBe(TranslationJobStatus::Succeeded);
    expect($job->failure_reason)->toBeNull();
    expect($job->result->translated_document_json['text'])
        ->toBe('<p>First paragraph content</p><p>第二段内容</p>');
    expect($job->result->meta_payload['full_fallback'])->toBeFalse();
    expect($job->result->meta_payload['fallback_ratio'])->toBe(0.5);
});

it('applies the same full fallback failure rule when the async provider is hermes', function () {
    Bus::fake();
    Cache::flush();
    config()->set('services.hermes', [
        'base_url' => 'https://hermes.example.test/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 120,
        'html_parallelism' => 2,
    ]);

    SystemSetting::query()->updateOrCreate(
        ['key' => 'translation.active_provider'],
        ['value' => 'hermes'],
    );

    $html = '<p><span>第一段内容</span><strong>第二段内容</strong></p>';
    $invalidSegmentDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
            1 => '第二段 content',
        ],
    ]);

    Http::fake([
        '*' => Http::response([
            'translated_document' => $invalidSegmentDocument,
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'gpt-5-mini',
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    $finalizeJob = null;
    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $dispatchedJob) use (&$finalizeJob) {
        $finalizeJob = $dispatchedJob;

        return true;
    });

    $finalizeJob->handle(
        app(TranslationJobService::class),
        app(AsyncTranslationResultHealthValidator::class),
        app(GlossaryHitPersister::class),
    );

    $job->refresh()->load('result');

    expect($job->status)->toBe(TranslationJobStatus::Failed);
    expect($job->failure_reason)->toBe('full_fallback: translated output fell back to the source content for all available HTML text nodes.');
    expect($job->result->meta_payload['provider'])->toBe('hermes');
    expect($job->result->meta_payload['provider_dispatch_mode'])->toBe('bounded_concurrent');
});

it('fails async hermes jobs early when the estimated runtime would exceed the worker budget', function () {
    Bus::fake();
    Cache::flush();
    config()->set('services.hermes', [
        'base_url' => 'https://hermes.example.test/v1',
        'api_key' => 'hermes-test-key',
        'profile' => 'chemical-news-translator',
        'model' => 'gpt-5-mini',
        'timeout' => 400,
        'html_parallelism' => 2,
    ]);

    SystemSetting::query()->updateOrCreate(
        ['key' => 'translation.active_provider'],
        ['value' => 'hermes'],
    );

    $paragraph = str_repeat('第一段内容', 160);
    $html = '<article>'.collect(range(1, 9))
        ->map(fn (int $index): string => sprintf('<p>%s%s</p>', $paragraph, $index))
        ->implode('').'</article>';

    Http::fake();

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    $processJob = new ProcessTranslationJob($job->id);

    try {
        $processJob->handle(
            app(TranslationJobService::class),
            app(TranslationService::class),
        );

        $this->fail('Expected the Hermes async job to be rejected by the budget guard.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toStartWith('job_budget_exceeded:');
        $processJob->failed($exception);
    }

    Http::assertNothingSent();

    $job->refresh();

    expect($job->status)->toBe(TranslationJobStatus::Failed);
    expect($job->failure_reason)->toStartWith('job_budget_exceeded:');
});

it('batches html text nodes into fewer upstream translation requests', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p><span>第一段内容</span><strong>第二段内容</strong><em>第三段内容</em></p>';
    $translatedDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
            1 => 'Second paragraph content',
            2 => 'Third paragraph content',
        ],
    ]);

    Http::fake([
        '*' => Http::response([
            'translated_document' => $translatedDocument,
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'openclaw/chemical-news-translator',
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(1);

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) {
        expect($finalizeJob->result['response']['translated_document']['text'])
            ->toBe('<p><span>First paragraph content</span><strong>Second paragraph content</strong><em>Third paragraph content</em></p>');
        expect($finalizeJob->result['response']['meta']['html_mode'])->toBeTrue();
        expect($finalizeJob->result['response']['meta']['translated_text_nodes'])->toBe(3);
        expect($finalizeJob->result['response']['meta']['fallback_text_nodes'])->toBe(0);
        expect($finalizeJob->result['response']['meta']['html_strategy'])->toBe('semantic_segment_parallel');
        expect($finalizeJob->result['response']['meta']['html_batch_count'])->toBe(1);
        expect($finalizeJob->result['response']['meta']['html_segment_count'])->toBe(1);

        return true;
    });
});

it('keeps short html payloads with many small text nodes in a single upstream batch', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $segments = [];
    $translatedDocument = [];
    $expectedSegments = [];

    foreach (range(0, 38) as $index) {
        $segments[] = sprintf('<span>短句%s</span>', $index);
        $translatedDocument['node_'.$index] = sprintf('Sentence %s', $index);
        $expectedSegments[] = sprintf('<span>Sentence %s</span>', $index);
    }

    $html = '<p>'.implode('', $segments).'</p>';
    $expectedHtml = '<p>'.implode('', $expectedSegments).'</p>';
    $semanticDocument = fakeSemanticSegmentDocument($html, [
        0 => array_map(
            static fn (int $index): string => sprintf('Sentence %s', $index),
            range(0, 38),
        ),
    ]);

    Http::fake([
        '*' => Http::response([
            'translated_document' => $semanticDocument,
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'openclaw/chemical-news-translator',
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'article_payload',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'body' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(1);

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) use ($expectedHtml) {
        expect($finalizeJob->result['response']['translated_document']['body'])->toBe($expectedHtml);
        expect($finalizeJob->result['response']['meta']['body_html_batch_count'])->toBe(1);
        expect($finalizeJob->result['response']['meta']['body_html_segment_count'])->toBe(1);
        expect($finalizeJob->result['response']['meta']['body_translated_text_nodes'])->toBe(39);

        return true;
    });
});

it('retries only missing html batch nodes instead of falling back for the whole batch', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p><span>第一段内容</span></p><p><strong>第二段内容</strong></p><p><em>第三段内容</em></p>';
    $partialBatchDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
        ],
        1 => [
            0 => 'Second paragraph content',
        ],
    ]);
    $singleSegmentDocument = fakeSemanticSegmentDocument($html, [
        2 => [
            0 => 'Third paragraph content',
        ],
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => $partialBatchDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200)
            ->push([
                'translated_document' => $singleSegmentDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(2);

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) {
        expect($finalizeJob->result['response']['translated_document']['text'])
            ->toBe('<p><span>First paragraph content</span></p><p><strong>Second paragraph content</strong></p><p><em>Third paragraph content</em></p>');
        expect($finalizeJob->result['response']['meta']['translated_text_nodes'])->toBe(3);
        expect($finalizeJob->result['response']['meta']['fallback_text_nodes'])->toBe(0);
        expect($finalizeJob->result['response']['meta']['html_fallback_segment_count'])->toBe(0);

        return true;
    });
});

it('retries invalid html batch nodes together before falling back to per-node translation', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p><span>第一段内容</span></p><p><strong>第二段内容</strong></p><p><em>第三段内容</em></p>';
    $invalidBatchDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
        ],
        1 => [
            0 => '第二段 content',
        ],
        2 => [
            0 => '第三段 content',
        ],
    ]);
    $retryBatchDocument = fakeSemanticSegmentDocument($html, [
        1 => [
            0 => 'Second paragraph content',
        ],
        2 => [
            0 => 'Third paragraph content',
        ],
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => $invalidBatchDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200)
            ->push([
                'translated_document' => $retryBatchDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(2);

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) {
        expect($finalizeJob->result['response']['translated_document']['text'])
            ->toBe('<p><span>First paragraph content</span></p><p><strong>Second paragraph content</strong></p><p><em>Third paragraph content</em></p>');
        expect($finalizeJob->result['response']['meta']['translated_text_nodes'])->toBe(3);
        expect($finalizeJob->result['response']['meta']['fallback_text_nodes'])->toBe(0);
        expect($finalizeJob->result['response']['meta']['html_fallback_segment_count'])->toBe(0);

        return true;
    });
});

it('treats html entity placeholders left in batch output as invalid and retries the affected segments', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p>&ldquo;第一段内容&rdquo;</p><p><strong>&middot;&nbsp;</strong>第二段内容</p>';
    $invalidBatchDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => '__HTML_ENTITY_0__First paragraph__HTML_ENTITY_1__',
        ],
        1 => [
            0 => '__HTML_ENTITY_0____HTML_ENTITY_1__',
            1 => 'Second paragraph content',
        ],
    ]);
    $retryBatchDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => '&ldquo;First paragraph&rdquo;',
        ],
        1 => [
            0 => '&middot;&nbsp;',
            1 => 'Second paragraph content',
        ],
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => $invalidBatchDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200)
            ->push([
                'translated_document' => $retryBatchDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'text' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(2);

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) {
        expect($finalizeJob->result['response']['translated_document']['text'])
            ->toBe('<p>&ldquo;First paragraph&rdquo;</p><p><strong>&middot;&nbsp;</strong>Second paragraph content</p>');
        expect($finalizeJob->result['response']['meta']['translated_text_nodes'])->toBe(3);
        expect($finalizeJob->result['response']['meta']['fallback_text_nodes'])->toBe(0);
        expect($finalizeJob->result['response']['meta']['html_fallback_segment_count'])->toBe(0);

        return true;
    });
});

it('retries html article body segments before falling back to original html', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p><span>第一段内容</span></p><p><strong>第二段内容</strong></p>';
    $invalidBatchDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
        ],
        1 => [
            0 => '第二段 content',
        ],
    ]);
    $singleSegmentDocument = fakeSemanticSegmentDocument($html, [
        1 => [
            0 => 'Second paragraph content',
        ],
    ]);

    Http::fake([
        '*' => Http::sequence()
            ->push([
                'translated_document' => $invalidBatchDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200)
            ->push([
                'translated_document' => $singleSegmentDocument,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'meta' => [
                    'schema_version' => 'v1',
                    'provider_model' => 'openclaw/chemical-news-translator',
                ],
            ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'article_payload',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'body' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(2);

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) {
        expect($finalizeJob->result['response']['translated_document']['body'])
            ->toBe('<p><span>First paragraph content</span></p><p><strong>Second paragraph content</strong></p>');
        expect($finalizeJob->result['response']['meta']['html_fields'])->toBe(['body']);
        expect($finalizeJob->result['response']['meta']['body_html_mode'])->toBeTrue();
        expect($finalizeJob->result['response']['meta']['body_fallback_text_nodes'])->toBe(0);
        expect($finalizeJob->result['response']['meta']['body_html_strategy'])->toBe('semantic_segment_parallel');
        expect($finalizeJob->result['response']['meta']['body_html_fallback_segment_count'])->toBe(0);

        return true;
    });
});

it('ignores a legacy github_models setting for async article translations and keeps using the long-text provider', function () {
    Bus::fake();
    Cache::flush();

    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'html_parallelism' => 2,
    ]);

    SystemSetting::query()->updateOrCreate([
        'key' => 'translation.active_provider',
    ], [
        'value' => 'github_models',
    ]);

    $html = '<p><span>第一段内容</span></p><p><strong>第二段内容</strong></p>';
    $translatedFirstSegment = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First paragraph content',
        ],
    ]);
    $translatedSecondSegment = fakeSemanticSegmentDocument($html, [
        1 => [
            0 => 'Second paragraph content',
        ],
    ]);
    $translatedBatchDocument = array_merge($translatedFirstSegment, $translatedSecondSegment);

    Http::fake([
        '*' => Http::response([
            'translated_document' => $translatedBatchDocument,
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'openclaw/chemical-news-translator',
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'article_payload',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'body' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        expect($request->url())->toContain('https://openclaw.example.test');

        return true;
    });

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) {
        expect($finalizeJob->result['response']['translated_document']['body'])
            ->toBe('<p><span>First paragraph content</span></p><p><strong>Second paragraph content</strong></p>');
        expect($finalizeJob->result['response']['meta']['provider'])->toBe('openclaw');
        expect($finalizeJob->result['response']['meta']['body_html_parallelism'])->toBe(2);
        expect($finalizeJob->result['response']['meta']['body_html_batch_text_limit'])->toBe(1800);
        expect($finalizeJob->result['response']['meta']['body_html_max_batch_segments'])->toBe(24);
        expect($finalizeJob->result['response']['meta']['body_html_batch_count'])->toBe(1);
        expect($finalizeJob->result['response']['meta']['body_html_fallback_segment_count'])->toBe(0);

        return true;
    });
});

it('normalizes chinese ordinal prefixes in translated html article body nodes', function () {
    Bus::fake();
    config()->set('services.openclaw', [
        'base_url' => 'https://openclaw.example.test',
        'api_key' => 'test-api-key',
        'translation_agent' => 'chemical-news-translator',
        'timeout' => 120,
        'retry_times' => 0,
    ]);

    $html = '<p>一是开展摸底评估。</p><p>二是制定改造方案。</p><p>三是推进提质升级。</p>';
    $translatedDocument = fakeSemanticSegmentDocument($html, [
        0 => [
            0 => 'First, conduct baseline assessments.',
        ],
        1 => [
            0 => 'Third, formulate renovation plans.',
        ],
        2 => [
            0 => 'Third, advance quality and performance upgrades.',
        ],
    ]);

    Http::fake([
        '*' => Http::response([
            'translated_document' => $translatedDocument,
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => 'openclaw/chemical-news-translator',
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'article_payload',
        'document_type' => 'chemical_news',
        'source_lang' => 'zh-CN',
        'target_lang' => 'en',
        'content' => [
            'body' => $html,
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    (new ProcessTranslationJob($job->id))->handle(
        app(TranslationJobService::class),
        app(TranslationService::class),
    );

    Bus::assertDispatched(FinalizeTranslationJob::class, function (FinalizeTranslationJob $finalizeJob) {
        expect($finalizeJob->result['response']['translated_document']['body'])
            ->toBe('<p>First, conduct baseline assessments.</p><p>Second, formulate renovation plans.</p><p>Third, advance quality and performance upgrades.</p>');

        return true;
    });
});

it('returns json while an async translation result is still pending', function () {
    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ]);

    $jobUuid = $response->json('job_uuid');

    $this->getJson("/api/demo/translate/jobs/{$jobUuid}/result")
        ->assertAccepted()
        ->assertJsonPath('job_uuid', $jobUuid)
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('message', 'Translation result is not ready yet.')
        ->assertJsonPath('error.code', 'translation_result_not_ready');
});

it('returns json failure details for failed async translation jobs', function () {
    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => [
            'text' => '乙烯价格上涨。',
        ],
    ]);

    $job = TranslationJob::query()
        ->where('job_uuid', $response->json('job_uuid'))
        ->firstOrFail();

    $job->forceFill([
        'status' => TranslationJobStatus::Failed,
        'failure_reason' => 'OpenClaw translated_document key [text] contains Chinese characters for English target output.',
        'finished_at' => now(),
    ])->save();

    AiInvocation::query()->create([
        'job_id' => $job->id,
        'agent_name' => 'chemical-news-translator',
        'request_payload' => [
            'execution_context' => [
                'provider' => 'hermes',
            ],
        ],
        'response_payload_summary' => null,
        'status' => 'failed',
        'duration_ms' => 1200,
        'error_message' => 'upstream timeout',
        'created_at' => now(),
    ]);

    $this->getJson("/api/demo/translate/jobs/{$job->job_uuid}")
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('source_document.text', '乙烯价格上涨。')
        ->assertJsonPath('translated_document', [])
        ->assertJsonPath('translation_provider', 'hermes')
        ->assertJsonPath('translation_agent', 'chemical-news-translator')
        ->assertJsonPath('error.code', 'translation_failed')
        ->assertJsonPath('error.reason', 'OpenClaw translated_document key [text] contains Chinese characters for English target output.');

    $this->getJson("/api/demo/translate/jobs/{$job->job_uuid}/result")
        ->assertStatus(409)
        ->assertJsonPath('job_uuid', $job->job_uuid)
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('message', 'Translation job failed.')
        ->assertJsonPath('error.code', 'translation_failed')
        ->assertJsonPath('error.reason', 'OpenClaw translated_document key [text] contains Chinese characters for English target output.');
});
