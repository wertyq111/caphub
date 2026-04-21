<?php

namespace App\Services\Translation;

use App\Enums\TranslationProvider;
use App\Enums\TranslationJobStatus;
use App\Models\DemoAccessLog;
use App\Models\TranslationJob;
use App\Services\TaskCenter\TranslationJobService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TranslationService
{
    protected const ASYNC_TEXT_CHUNK_THRESHOLD = 1800;

    protected const ASYNC_TEXT_CHUNK_SIZE = 1200;

    protected const ASYNC_HTML_BATCH_TEXT_LIMIT = 6000;

    protected const ASYNC_HTML_MAX_BATCH_NODES = 128;

    protected const ASYNC_HTML_SEGMENT_TARGET_TEXT = 300;

    protected const ASYNC_HTML_SEGMENT_TEXT_LIMIT = 600;

    protected const ASYNC_HTML_SEGMENT_BATCH_TEXT_LIMIT = 1800;

    protected const ASYNC_HTML_MAX_BATCH_SEGMENTS = 24;

    protected const ASYNC_HTML_RETRY_BATCH_TEXT_LIMIT = 900;

    protected const ASYNC_HTML_RETRY_MAX_BATCH_SEGMENTS = 6;

    protected const ASYNC_JOB_TIMEOUT_SECONDS = 900;

    protected const ASYNC_JOB_TIMEOUT_GUARD_RATIO = 0.85;

    /**
     * 初始化翻译服务依赖，参数：客户端、模式解析、结果持久化与术语命中持久化服务。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected TranslationGatewayRouter $gateway,
        protected TranslationJobService $translationJobService,
        protected TranslationModeResolver $modeResolver,
        protected TranslationResultPersister $resultPersister,
        protected GlossaryHitPersister $glossaryHitPersister,
        protected HtmlTextNodeTranslator $htmlTextNodeTranslator,
    ) {}

    /**
     * 执行同步翻译流程，参数：$normalizedRequest 标准化请求体。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $normalizedRequest
     * @return array<string, mixed>
     */
    public function translateSync(array $normalizedRequest): array
    {
        $provider = $this->providerForSyncRequest($normalizedRequest);

        return $this->gateway->usingProvider($provider, function () use ($normalizedRequest) {
            $cacheKey = $this->syncCacheKey($normalizedRequest);
            $lockSeconds = $this->syncCacheLockSeconds();
            $waitSeconds = $this->syncCacheLockWaitSeconds();
            $cacheContext = $this->syncCacheContext($normalizedRequest, $cacheKey);

            return Cache::lock($cacheKey.':lock', $lockSeconds)->block($waitSeconds, function () use ($cacheKey, $normalizedRequest, $cacheContext) {
                $cachedResponse = Cache::get($cacheKey);

                if (is_array($cachedResponse)) {
                    Log::info('sync_translation_cache_hit', $cacheContext);
                    $this->recordDemoAccess('sync_translation_cache_hit');

                    return $this->syncResultForResponse($normalizedRequest, $this->decorateResponse($cachedResponse, true));
                }

                Log::info('sync_translation_cache_miss', $cacheContext);
                $startedAt = now();
                $job = $this->createSyncJob($normalizedRequest, $startedAt);

                try {
                    $response = $this->gateway->translatePayload(
                        (array) $normalizedRequest['openclaw_payload'],
                        $job->id,
                    );
                    $finishedAt = now();

                    $result = DB::transaction(function () use ($normalizedRequest, $response, $job, $finishedAt) {
                        $this->resultPersister->persist($job, $response, false);
                        $this->glossaryHitPersister->persistForJob(
                            $job,
                            (array) ($response['glossary_hits'] ?? []),
                            $normalizedRequest['domain'] ?? null,
                        );
                        $this->translationJobService->markSucceeded($job, $finishedAt);
                        $this->recordDemoAccess('sync_translation_cache_miss', $job->id);

                        return $this->syncResultForResponse($normalizedRequest, $this->decorateResponse($response, false));
                    });

                    $stored = Cache::put($cacheKey, $response, now()->addHour());
                    Log::info('sync_translation_cache_store', array_merge($cacheContext, [
                        'stored' => $stored,
                        'job_id' => $job->id,
                    ]));

                    return $result;
                } catch (Throwable $throwable) {
                    $this->translationJobService->markFailed($job, $throwable->getMessage(), now());

                    throw $throwable;
                }
            });
        });
    }

    /**
     * 计算同步翻译缓存锁时长，确保相同请求在上游超时窗口内不会重复穿透。
     * @since 2026-04-09
     * @author zhouxufeng
     */
    protected function syncCacheLockSeconds(): int
    {
        $upstreamTimeout = $this->gateway->timeout();

        return max($upstreamTimeout + 15, 45);
    }

    /**
     * 计算同步翻译缓存锁等待时长，确保后续同请求优先复用首个请求结果。
     * @since 2026-04-09
     * @author zhouxufeng
     */
    protected function syncCacheLockWaitSeconds(): int
    {
        return max($this->syncCacheLockSeconds() - 5, 10);
    }

    /**
     * 执行异步任务翻译，参数：$jobId 任务 ID。
     * @since 2026-04-02
     * @author zhouxufeng
     * @return array<string, mixed>
     */
    public function translateAsyncJob(int $jobId): array
    {
        $job = TranslationJob::query()->find($jobId);

        if (! $job) {
            throw new RuntimeException(sprintf('Translation job [%d] was not found.', $jobId));
        }

        $inputDocument = array_filter([
            'text' => $job->source_text,
            'title' => $job->source_title,
            'summary' => $job->source_summary,
            'body' => $job->source_body,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $provider = $this->providerForAsyncJob($job, $inputDocument);

        return $this->gateway->usingProvider($provider, function () use ($job, $inputDocument) {
            if ($this->shouldTranslateAsyncHtmlPlainText($job, $inputDocument)) {
                return [
                    'mode' => 'async',
                    'input_type' => $job->input_type,
                    'response' => $this->translateAsyncHtmlPlainText($job, (string) $inputDocument['text']),
                ];
            }

            if ($this->shouldChunkAsyncPlainText($job, $inputDocument)) {
                return [
                    'mode' => 'async',
                    'input_type' => $job->input_type,
                    'response' => $this->translateChunkedAsyncPlainText($job, (string) $inputDocument['text']),
                ];
            }

            $htmlDocumentFields = $this->asyncHtmlDocumentFields($job, $inputDocument);
            if ($htmlDocumentFields !== []) {
                return [
                    'mode' => 'async',
                    'input_type' => $job->input_type,
                    'response' => $this->translateAsyncDocumentWithHtmlFields($job, $inputDocument, $htmlDocumentFields),
                ];
            }

            $response = $this->gateway->translate([
                ...$inputDocument,
                'source_lang' => $job->source_lang,
                'target_lang' => $job->target_lang,
            ], jobId: $job->id);

            return [
                'mode' => 'async',
                'input_type' => $job->input_type,
                'response' => $response,
            ];
        });
    }

    /**
     * 同步接口只把真正的短纯文本固定到 GitHub Models，其余仍按后台长文本接口走。
     *
     * @param  array<string, mixed>  $normalizedRequest
     */
    protected function providerForSyncRequest(array $normalizedRequest): TranslationProvider
    {
        $text = data_get($normalizedRequest, 'input_document.text');

        if (is_string($text) && $this->isShortPlainTextForGitHub($normalizedRequest['input_type'] ?? null, $text)) {
            return TranslationProvider::GitHubModels;
        }

        return $this->gateway->activeProvider();
    }

    /**
     * 异步任务里，只有短纯文本固定走 GitHub Models；长文本、HTML、article payload 都走后台选择的长文本接口。
     *
     * @param  array<string, string>  $inputDocument
     */
    protected function providerForAsyncJob(TranslationJob $job, array $inputDocument): TranslationProvider
    {
        $text = $inputDocument['text'] ?? null;

        if (is_string($text) && $this->isShortPlainTextForGitHub($job->input_type, $text)) {
            return TranslationProvider::GitHubModels;
        }

        return $this->gateway->activeProvider();
    }

    protected function isShortPlainTextForGitHub(mixed $inputType, string $text): bool
    {
        if ($inputType !== 'plain_text') {
            return false;
        }

        if ($this->htmlTextNodeTranslator->looksLikeHtml($text)) {
            return false;
        }

        return mb_strlen($text) <= self::ASYNC_TEXT_CHUNK_THRESHOLD;
    }

    /**
     * 组装同步翻译统一返回结构，参数：$normalizedRequest 标准化请求，$response 翻译响应。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $normalizedRequest
     * @return array<string, mixed>
     */
    protected function syncResultForResponse(array $normalizedRequest, array $response): array
    {
        return [
            'mode' => $this->modeResolver->resolve($normalizedRequest),
            'input_type' => $normalizedRequest['input_type'] ?? 'plain_text',
            'response' => $response,
        ];
    }

    /**
     * 计算同步翻译缓存键，参数：$normalizedRequest 标准化请求。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $normalizedRequest
     */
    protected function syncCacheKey(array $normalizedRequest): string
    {
        return 'demo:sync-translation:'.hash('sha256', json_encode([
            'input_type' => $normalizedRequest['input_type'] ?? 'plain_text',
            'document_type' => $normalizedRequest['document_type'] ?? null,
            'openclaw_payload' => $normalizedRequest['openclaw_payload'] ?? [],
            'translation_provider' => $this->gateway->activeProvider()->value,
            'translation_agent' => $this->gateway->activeAgent(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 组装同步缓存观测上下文，避免后续再靠猜缓存为什么 miss。
     * @since 2026-04-14
     * @author zhouxufeng
     * @param  array<string, mixed>  $normalizedRequest
     * @return array<string, mixed>
     */
    protected function syncCacheContext(array $normalizedRequest, string $cacheKey): array
    {
        return [
            'cache_key_hash' => hash('sha256', $cacheKey),
            'provider' => $this->gateway->activeProvider()->value,
            'agent' => $this->gateway->activeAgent(),
            'input_type' => $normalizedRequest['input_type'] ?? 'plain_text',
            'document_type' => $normalizedRequest['document_type'] ?? null,
            'source_lang' => $normalizedRequest['source_lang'] ?? null,
            'target_lang' => $normalizedRequest['target_lang'] ?? null,
            'document_keys' => array_keys((array) ($normalizedRequest['input_document'] ?? [])),
            'document_lengths' => $this->syncDocumentLengths((array) ($normalizedRequest['input_document'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, int>
     */
    protected function syncDocumentLengths(array $document): array
    {
        return collect($document)
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): int => mb_strlen($value))
            ->all();
    }

    /**
     * 为响应补充缓存命中标记，参数：$response 原响应，$cacheHit 是否命中缓存。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function decorateResponse(array $response, bool $cacheHit): array
    {
        $response['meta'] = array_merge((array) ($response['meta'] ?? []), [
            'cache_hit' => $cacheHit,
        ]);

        return $response;
    }

    /**
     * 创建同步翻译任务记录，参数：$normalizedRequest 标准化请求，$startedAt 翻译开始时间，$finishedAt 翻译完成时间。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $normalizedRequest
     */
    protected function createSyncJob(
        array $normalizedRequest,
        ?\Illuminate\Support\Carbon $startedAt = null,
    ): TranslationJob {
        $now = now();

        return TranslationJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'mode' => $this->modeResolver->resolve($normalizedRequest),
            'status' => TranslationJobStatus::Processing,
            'failure_reason' => null,
            'input_type' => $normalizedRequest['input_type'] ?? 'plain_text',
            'document_type' => $normalizedRequest['document_type'] ?? null,
            'source_lang' => $normalizedRequest['source_lang'] ?? '',
            'target_lang' => $normalizedRequest['target_lang'] ?? '',
            'source_text' => $normalizedRequest['source_text'] ?? null,
            'source_title' => $normalizedRequest['source_title'] ?? null,
            'source_summary' => $normalizedRequest['source_summary'] ?? null,
            'source_body' => $normalizedRequest['source_body'] ?? null,
            'started_at' => $startedAt ?? $now,
        ]);
    }

    /**
     * 记录 Demo 接口访问日志，参数：$action 行为标识，$jobId 可选任务 ID。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  string|null  $jobId
     */
    protected function recordDemoAccess(string $action, ?int $jobId = null): void
    {
        if (! app()->bound('request')) {
            return;
        }

        DemoAccessLog::query()->create([
            'ip_hash' => hash('sha256', (string) request()->ip()),
            'user_agent_hash' => hash('sha256', (string) request()->userAgent()),
            'action' => $action,
            'job_id' => $jobId,
        ]);
    }

    /**
     * 判断异步任务是否需要按长文本分段处理，参数：$job 任务模型，$inputDocument 输入文档。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, string>  $inputDocument
     */
    protected function shouldChunkAsyncPlainText(TranslationJob $job, array $inputDocument): bool
    {
        if ($job->input_type !== 'plain_text') {
            return false;
        }

        $text = $inputDocument['text'] ?? null;

        return is_string($text) && mb_strlen($text) > self::ASYNC_TEXT_CHUNK_THRESHOLD;
    }

    /**
     * 判断异步纯文本任务是否需要按 HTML 文本节点翻译，参数：$job 任务模型，$inputDocument 输入文档。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<string, string>  $inputDocument
     */
    protected function shouldTranslateAsyncHtmlPlainText(TranslationJob $job, array $inputDocument): bool
    {
        if ($job->input_type !== 'plain_text') {
            return false;
        }

        $text = $inputDocument['text'] ?? null;

        return is_string($text) && $this->htmlTextNodeTranslator->looksLikeHtml($text);
    }

    /**
     * 执行异步长文本分段翻译并合并结果，参数：$job 任务模型，$text 原始长文本。
     * @since 2026-04-02
     * @author zhouxufeng
     * @return array<string, mixed>
     */
    protected function translateChunkedAsyncPlainText(TranslationJob $job, string $text): array
    {
        $chunks = $this->splitPlainTextIntoChunks($text);
        $this->assertWithinAsyncJobBudget($job, count($chunks), 1, 'sequential_chunks');
        $translatedChunks = [];
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $providerLatencyMs = 0;
        $retryCount = 0;
        $meta = [
            'schema_version' => 'v1',
            'provider_model' => $this->gateway->activeAgent(),
            'provider' => $this->gateway->activeProvider()->value,
            'chunked' => true,
            'chunk_count' => count($chunks),
            'segment_count' => count($chunks),
            'provider_dispatch_mode' => 'sequential_chunks',
        ];

        foreach ($chunks as $chunk) {
            $response = $this->gateway->translate([
                'text' => $chunk,
                'source_lang' => $job->source_lang,
                'target_lang' => $job->target_lang,
            ], jobId: $job->id);

            $translatedChunks[] = (string) data_get($response, 'translated_document.text', '');
            $glossaryHits = [...$glossaryHits, ...(array) ($response['glossary_hits'] ?? [])];
            $riskFlags = [...$riskFlags, ...(array) ($response['risk_flags'] ?? [])];
            $notes = [...$notes, ...(array) ($response['notes'] ?? [])];
            $providerLatencyMs += $this->metaInt($response, 'provider_latency_ms');
            $retryCount += $this->metaInt($response, 'retry_count');
            $meta = array_merge($meta, array_filter((array) ($response['meta'] ?? []), static fn ($value, $key): bool => $key !== 'schema_version', ARRAY_FILTER_USE_BOTH));
        }

        $meta['provider_latency_ms'] = $providerLatencyMs;
        $meta['retry_count'] = $retryCount;
        $meta['segment_count'] = count($chunks);
        $meta['provider_dispatch_mode'] = 'sequential_chunks';

        return [
            'translated_document' => [
                'text' => implode("\n\n", array_filter($translatedChunks, static fn (string $chunk): bool => $chunk !== '')),
            ],
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
            'meta' => $meta,
        ];
    }

    /**
     * 按 HTML 文本节点翻译异步纯文本内容，参数：$job 任务模型，$text HTML 文本。
     * @since 2026-04-03
     * @author zhouxufeng
     * @return array<string, mixed>
     */
    protected function translateAsyncHtmlPlainText(TranslationJob $job, string $text): array
    {
        $htmlTranslation = $this->translateAsyncHtmlContent($job, $text);

        return [
            'translated_document' => [
                'text' => $htmlTranslation['text'],
            ],
            'glossary_hits' => $htmlTranslation['glossary_hits'],
            'risk_flags' => $htmlTranslation['risk_flags'],
            'notes' => $htmlTranslation['notes'],
            'meta' => $htmlTranslation['meta'],
        ];
    }

    /**
     * 判断异步文档任务中哪些字段需要走 HTML 节点级翻译，参数：$job 任务模型，$inputDocument 输入文档。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<string, string>  $inputDocument
     * @return array<int, string>
     */
    protected function asyncHtmlDocumentFields(TranslationJob $job, array $inputDocument): array
    {
        if ($job->input_type === 'plain_text') {
            return [];
        }

        $htmlFields = [];

        foreach ($inputDocument as $field => $value) {
            if (! is_string($value) || ! $this->htmlTextNodeTranslator->looksLikeHtml($value)) {
                continue;
            }

            $htmlFields[] = $field;
        }

        return $htmlFields;
    }

    /**
     * 混合处理常规字段与 HTML 字段的异步翻译，参数：$job 任务模型，$inputDocument 输入文档，$htmlFields HTML 字段列表。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<string, string>  $inputDocument
     * @param  array<int, string>  $htmlFields
     * @return array<string, mixed>
     */
    protected function translateAsyncDocumentWithHtmlFields(TranslationJob $job, array $inputDocument, array $htmlFields): array
    {
        $translatedDocument = [];
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $providerLatencyMs = 0;
        $retryCount = 0;
        $segmentCount = 0;
        $dispatchMode = $htmlFields !== [] ? 'bounded_concurrent' : 'single';
        $meta = [
            'schema_version' => 'v1',
            'provider_model' => $this->gateway->activeAgent(),
            'provider' => $this->gateway->activeProvider()->value,
            'html_fields' => array_values($htmlFields),
        ];

        $plainFields = array_diff_key($inputDocument, array_flip($htmlFields));

        if ($plainFields !== []) {
            $response = $this->gateway->translate([
                ...$plainFields,
                'source_lang' => $job->source_lang,
                'target_lang' => $job->target_lang,
            ], jobId: $job->id);

            $translatedDocument = array_merge($translatedDocument, (array) ($response['translated_document'] ?? []));
            $this->mergeTranslationSignals($response, $glossaryHits, $riskFlags, $notes);
            $providerLatencyMs += $this->metaInt($response, 'provider_latency_ms');
            $retryCount += $this->metaInt($response, 'retry_count');
            $dispatchMode = $this->metaString($response, 'provider_dispatch_mode', $dispatchMode);
            $meta = array_merge(
                $meta,
                array_filter((array) ($response['meta'] ?? []), static fn ($value, $key): bool => $key !== 'schema_version', ARRAY_FILTER_USE_BOTH),
            );
        }

        foreach ($htmlFields as $field) {
            $htmlTranslation = $this->translateAsyncHtmlContent($job, (string) $inputDocument[$field]);
            $translatedDocument[$field] = $htmlTranslation['text'];
            $this->mergeTranslationSignals($htmlTranslation, $glossaryHits, $riskFlags, $notes);
            $providerLatencyMs += $this->metaInt($htmlTranslation, 'provider_latency_ms');
            $retryCount += $this->metaInt($htmlTranslation, 'retry_count');
            $segmentCount += $this->metaInt($htmlTranslation, 'segment_count');
            $dispatchMode = $this->metaString($htmlTranslation, 'provider_dispatch_mode', $dispatchMode);

            foreach ((array) ($htmlTranslation['meta'] ?? []) as $metaKey => $metaValue) {
                if ($metaKey === 'schema_version' || $metaKey === 'provider_model') {
                    continue;
                }

                $meta[$field.'_'.$metaKey] = $metaValue;
            }
        }

        $meta['provider_latency_ms'] = $providerLatencyMs;
        $meta['retry_count'] = $retryCount;
        $meta['segment_count'] = $segmentCount;
        $meta['provider_dispatch_mode'] = $dispatchMode;

        return [
            'translated_document' => $translatedDocument,
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
            'meta' => $meta,
        ];
    }

    /**
     * 按 HTML 文本节点翻译单个 HTML 内容字段，参数：$job 任务模型，$text HTML 文本。
     * @since 2026-04-03
     * @author zhouxufeng
     * @return array{text: string, glossary_hits: array<int, mixed>, risk_flags: array<int, mixed>, notes: array<int, mixed>, meta: array<string, mixed>}
     */
    protected function translateAsyncHtmlContent(TranslationJob $job, string $text): array
    {
        $compiled = $this->htmlTextNodeTranslator->compileSemanticSegments(
            $text,
            self::ASYNC_HTML_SEGMENT_TARGET_TEXT,
            self::ASYNC_HTML_SEGMENT_TEXT_LIMIT,
        );

        $translatedNodeTexts = [];
        $translatedTextNodes = 0;
        $fallbackTextNodes = 0;
        $translation = $this->translateHtmlSegmentBatches($job, $compiled['nodes'], $compiled['segments']);

        foreach ($compiled['nodes'] as $nodeIndex => $node) {
            $result = $this->htmlTextNodeTranslator->hydrateNodeText(
                $node,
                $translation['node_texts'][$nodeIndex] ?? null,
            );

            $translatedNodeTexts[$nodeIndex] = $result['text'];
            $translatedTextNodes += $result['translated'] ? 1 : 0;
            $fallbackTextNodes += $result['fallback'] ? 1 : 0;
        }

        return [
            'text' => $this->htmlTextNodeTranslator->render($compiled['parts'], $translatedNodeTexts),
            'glossary_hits' => $translation['glossary_hits'],
            'risk_flags' => $translation['risk_flags'],
            'notes' => $translation['notes'],
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => $translation['provider_model'] ?? $this->gateway->activeAgent(),
                'provider' => $this->gateway->activeProvider()->value,
                'html_mode' => true,
                'html_strategy' => 'semantic_segment_parallel',
                'html_segment_count' => count($compiled['segments']),
                'html_batch_count' => $translation['batch_count'],
                'html_parallelism' => $this->htmlBatchParallelism(),
                'html_batch_text_limit' => $this->htmlSegmentBatchTextLimit(),
                'html_max_batch_segments' => $this->htmlMaxBatchSegments(),
                'html_retry_batch_text_limit' => $this->htmlRetryBatchTextLimit(),
                'html_retry_max_batch_segments' => $this->htmlRetryMaxBatchSegments(),
                'html_fallback_segment_count' => $translation['fallback_segment_count'],
                'translated_text_nodes' => $translatedTextNodes,
                'fallback_text_nodes' => $fallbackTextNodes,
                'provider_latency_ms' => $translation['provider_latency_ms'],
                'retry_count' => $translation['retry_count'],
                'segment_count' => count($compiled['segments']),
                'provider_dispatch_mode' => 'bounded_concurrent',
            ],
        ];
    }

    /**
     * 将语义段按长度和数量切分为批次，参数：$segments 段列表，可选限制索引。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<int, array{visible_length: int}>  $segments
     * @param  array<int, int>|null  $segmentIndexes
     * @return array<int, array<int, int>>
     */
    protected function chunkHtmlSegments(
        array $segments,
        ?array $segmentIndexes = null,
        ?int $textLimit = null,
        ?int $maxSegments = null,
    ): array {
        $textLimit ??= $this->htmlSegmentBatchTextLimit();
        $maxSegments ??= $this->htmlMaxBatchSegments();
        $segmentIndexes ??= array_keys($segments);
        $batches = [];
        $buffer = [];
        $bufferLength = 0;

        foreach ($segmentIndexes as $segmentIndex) {
            $segmentLength = (int) ($segments[$segmentIndex]['visible_length'] ?? 0);

            if ($buffer !== [] && ($bufferLength + $segmentLength > $textLimit || count($buffer) >= $maxSegments)) {
                $batches[] = $buffer;
                $buffer = [];
                $bufferLength = 0;
            }

            $buffer[] = $segmentIndex;
            $bufferLength += $segmentLength;
        }

        if ($buffer !== []) {
            $batches[] = $buffer;
        }

        return $batches;
    }

    /**
     * 执行 HTML 语义段批次翻译并在失败时做分层回退。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<int, array{
     *      original: string,
     *      leading_whitespace: string,
     *      core_text: string,
     *      trailing_whitespace: string,
     *      entity_map: array<string, string>
     *  }>  $nodes
     * @param  array<int, array{
     *      index: int,
     *      node_indexes: array<int, int>,
     *      visible_length: int,
     *      source_text: string
     *  }>  $segments
     * @return array{
     *      node_texts: array<int, string>,
     *      glossary_hits: array<int, mixed>,
     *      risk_flags: array<int, mixed>,
     *      notes: array<int, mixed>,
     *      batch_count: int,
     *      fallback_segment_count: int,
     *      provider_model: string|null,
     *      provider_latency_ms: int,
     *      retry_count: int
     * }
     */
    protected function translateHtmlSegmentBatches(TranslationJob $job, array $nodes, array $segments): array
    {
        if ($segments === []) {
            return [
                'node_texts' => [],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'batch_count' => 0,
                'fallback_segment_count' => 0,
                'provider_model' => null,
                'provider_latency_ms' => 0,
                'retry_count' => 0,
            ];
        }

        $batches = $this->chunkHtmlSegments($segments);
        $parallelism = $this->htmlBatchParallelism();
        $this->assertWithinAsyncJobBudget($job, count($batches), $parallelism, 'bounded_concurrent');
        $requests = [];

        foreach ($batches as $batchIndex => $segmentIndexes) {
            $requests[$batchIndex] = $this->makeHtmlSegmentRequest($job, $segments, $segmentIndexes, [
                'batch_index' => $batchIndex,
                'segment_count' => count($segmentIndexes),
                'html_parallelism' => $parallelism,
                'html_request_type' => 'segment_batch',
            ]);
        }

        $results = $this->gateway->translateDocumentsConcurrently(
            $requests,
            $parallelism,
            false,
            true,
        );

        $nodeTexts = [];
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $fallbackSegmentCount = 0;
        $providerModel = null;
        $providerLatencyMs = 0;
        $retryCount = 0;

        foreach ($batches as $batchIndex => $segmentIndexes) {
            $consumed = $this->consumeHtmlSegmentRequestResult(
                $job,
                $nodes,
                $segments,
                $segmentIndexes,
                $results[$batchIndex] ?? [],
            );

            $nodeTexts = [...$nodeTexts, ...$consumed['node_texts']];
            $this->mergeTranslationSignals($consumed, $glossaryHits, $riskFlags, $notes);
            $fallbackSegmentCount += $consumed['fallback_segment_count'];
            $providerModel ??= $consumed['provider_model'];
            $providerLatencyMs += $consumed['provider_latency_ms'];
            $retryCount += $consumed['retry_count'];
        }

        return [
            'node_texts' => $nodeTexts,
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
            'batch_count' => count($batches),
            'fallback_segment_count' => $fallbackSegmentCount,
            'provider_model' => $providerModel,
            'provider_latency_ms' => $providerLatencyMs,
            'retry_count' => $retryCount,
        ];
    }

    /**
     * 构建 HTML 语义段翻译请求。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<int, array{
     *      node_indexes: array<int, int>,
     *      source_text: string
     *  }>  $segments
     * @param  array<int, int>  $segmentIndexes
     * @param  array<string, mixed>  $context
     * @return array{
     *      payload: array<string, mixed>,
     *      job_id: int,
     *      context: array<string, mixed>,
     *      enforce_target_language: bool,
     *      allow_partial_translated_document: bool
     * }
     */
    protected function makeHtmlSegmentRequest(TranslationJob $job, array $segments, array $segmentIndexes, array $context): array
    {
        $document = [];
        $placeholderTokens = [];

        foreach ($segmentIndexes as $segmentIndex) {
            $segment = $segments[$segmentIndex];
            $document['segment_'.$segmentIndex] = (string) ($segment['source_text'] ?? '');
            $placeholderTokens = [
                ...$placeholderTokens,
                ...$this->htmlTextNodeTranslator->segmentPlaceholderTokens($segment),
            ];
        }

        return [
            'payload' => $this->buildTranslationPayload($job, $document, [
                'preserve_placeholders' => array_values(array_unique($placeholderTokens)),
            ]),
            'job_id' => $job->id,
            'context' => $context,
            'enforce_target_language' => false,
            'allow_partial_translated_document' => true,
        ];
    }

    /**
     * 消费一组 HTML 语义段请求结果，并在异常时触发分层回退。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<int, array{
     *      original: string,
     *      leading_whitespace: string,
     *      core_text: string,
     *      trailing_whitespace: string,
     *      entity_map: array<string, string>
     *  }>  $nodes
     * @param  array<int, array{
     *      node_indexes: array<int, int>,
     *      source_text: string
     *  }>  $segments
     * @param  array<int, int>  $segmentIndexes
     * @param  array{response?: array<string, mixed>, exception?: Throwable}  $result
     * @return array{
     *      node_texts: array<int, string>,
     *      glossary_hits: array<int, mixed>,
     *      risk_flags: array<int, mixed>,
     *      notes: array<int, mixed>,
     *      fallback_segment_count: int,
     *      provider_model: string|null,
     *      provider_latency_ms: int,
     *      retry_count: int
     * }
     */
    protected function consumeHtmlSegmentRequestResult(
        TranslationJob $job,
        array $nodes,
        array $segments,
        array $segmentIndexes,
        array $result,
    ): array {
        $parsed = isset($result['response']) && is_array($result['response'])
            ? $this->parseHtmlSegmentRequestResult($job, $nodes, $segments, $segmentIndexes, $result['response'])
            : [
                'node_texts' => [],
                'invalid_segments' => $segmentIndexes,
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'provider_model' => null,
                'provider_latency_ms' => 0,
                'retry_count' => 0,
            ];

        $retry = $this->retryInvalidHtmlSegments(
            $job,
            $nodes,
            $segments,
            $parsed['invalid_segments'],
        );

        return [
            'node_texts' => [...$parsed['node_texts'], ...$retry['node_texts']],
            'glossary_hits' => [...$parsed['glossary_hits'], ...$retry['glossary_hits']],
            'risk_flags' => [...$parsed['risk_flags'], ...$retry['risk_flags']],
            'notes' => [...$parsed['notes'], ...$retry['notes']],
            'fallback_segment_count' => $retry['fallback_segment_count'],
            'provider_model' => $parsed['provider_model'] ?? $retry['provider_model'],
            'provider_latency_ms' => $parsed['provider_latency_ms'] + $retry['provider_latency_ms'],
            'retry_count' => $parsed['retry_count'] + $retry['retry_count'],
        ];
    }

    /**
     * 解析 HTML 段批次响应，返回已成功解码的节点文本与仍需回退的段索引。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<int, array{
     *      original: string,
     *      leading_whitespace: string,
     *      core_text: string,
     *      trailing_whitespace: string,
     *      entity_map: array<string, string>
     *  }>  $nodes
     * @param  array<int, array{node_indexes: array<int, int>}>  $segments
     * @param  array<int, int>  $segmentIndexes
     * @param  array<string, mixed>  $response
     * @return array{
     *      node_texts: array<int, string>,
     *      invalid_segments: array<int, int>,
     *      glossary_hits: array<int, mixed>,
     *      risk_flags: array<int, mixed>,
     *      notes: array<int, mixed>,
     *      provider_model: string|null,
     *      provider_latency_ms: int,
     *      retry_count: int
     * }
     */
    protected function parseHtmlSegmentRequestResult(
        TranslationJob $job,
        array $nodes,
        array $segments,
        array $segmentIndexes,
        array $response,
    ): array {
        $translatedDocument = (array) ($response['translated_document'] ?? []);
        $nodeTexts = [];
        $invalidSegments = [];

        foreach ($segmentIndexes as $segmentIndex) {
            $segmentKey = 'segment_'.$segmentIndex;
            $encodedText = $translatedDocument[$segmentKey] ?? null;

            if (! is_string($encodedText)) {
                $invalidSegments[] = $segmentIndex;

                continue;
            }

            $decodedNodeTexts = $this->translateHtmlSegmentResponseToNodeTexts(
                $job,
                $nodes,
                $segments[$segmentIndex],
                $encodedText,
            );

            if ($decodedNodeTexts === null) {
                $invalidSegments[] = $segmentIndex;

                continue;
            }

            $nodeTexts = [...$nodeTexts, ...$decodedNodeTexts];
        }

        return [
            'node_texts' => $nodeTexts,
            'invalid_segments' => array_values(array_unique($invalidSegments)),
            'glossary_hits' => (array) ($response['glossary_hits'] ?? []),
            'risk_flags' => (array) ($response['risk_flags'] ?? []),
            'notes' => (array) ($response['notes'] ?? []),
            'provider_model' => data_get($response, 'meta.provider_model'),
            'provider_latency_ms' => $this->metaInt($response, 'provider_latency_ms'),
            'retry_count' => $this->metaInt($response, 'retry_count'),
        ];
    }

    /**
     * 将段级译文解码为节点级译文，失败时返回 null。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<int, array{
     *      core_text: string
     *  }>  $nodes
     * @param  array{node_indexes: array<int, int>}  $segment
     * @return array<int, string>|null
     */
    protected function translateHtmlSegmentResponseToNodeTexts(
        TranslationJob $job,
        array $nodes,
        array $segment,
        string $encodedText,
    ): ?array {
        $decoded = $this->htmlTextNodeTranslator->decodeSegmentNodeTexts($segment, $encodedText);
        $expectedNodeIndexes = array_values((array) ($segment['node_indexes'] ?? []));

        if (count($decoded) !== count($expectedNodeIndexes)) {
            return null;
        }

        $normalized = [];

        foreach ($expectedNodeIndexes as $nodeIndex) {
            if (! array_key_exists($nodeIndex, $decoded)) {
                return null;
            }

            $translatedText = $this->normalizeTranslatedOrdinalPrefix(
                (string) ($nodes[$nodeIndex]['core_text'] ?? ''),
                (string) $decoded[$nodeIndex],
                $job->target_lang,
            );

            if ($this->translatedTextContainsSourceResidue($translatedText, $job->target_lang)) {
                return null;
            }

            $normalized[$nodeIndex] = $translatedText;
        }

        return $normalized;
    }

    /**
     * 对失败的 HTML 语义段先缩小批次重试，再降级到单段翻译。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<int, array{
     *      core_text: string
     *  }>  $nodes
     * @param  array<int, array{
     *      node_indexes: array<int, int>,
     *      source_text: string,
     *      visible_length: int
     *  }>  $segments
     * @param  array<int, int>  $segmentIndexes
     * @return array{
     *      node_texts: array<int, string>,
     *      glossary_hits: array<int, mixed>,
     *      risk_flags: array<int, mixed>,
     *      notes: array<int, mixed>,
     *      fallback_segment_count: int,
     *      provider_model: string|null,
     *      provider_latency_ms: int,
     *      retry_count: int
     * }
     */
    protected function retryInvalidHtmlSegments(
        TranslationJob $job,
        array $nodes,
        array $segments,
        array $segmentIndexes,
    ): array {
        $segmentIndexes = array_values(array_unique($segmentIndexes));

        if ($segmentIndexes === []) {
            return [
                'node_texts' => [],
                'glossary_hits' => [],
                'risk_flags' => [],
                'notes' => [],
                'fallback_segment_count' => 0,
                'provider_model' => null,
                'provider_latency_ms' => 0,
                'retry_count' => 0,
            ];
        }

        $nodeTexts = [];
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $fallbackSegmentCount = 0;
        $providerModel = null;
        $providerLatencyMs = 0;
        $retryCount = 0;
        $remainingSegments = $segmentIndexes;
        $parallelism = $this->htmlBatchParallelism();

        if (count($segmentIndexes) > 1) {
            $retryBatches = $this->chunkHtmlSegments(
                $segments,
                $segmentIndexes,
                $this->htmlRetryBatchTextLimit(),
                $this->htmlRetryMaxBatchSegments(),
            );

            $requests = [];
            foreach ($retryBatches as $batchIndex => $batchSegmentIndexes) {
                $requests[$batchIndex] = $this->makeHtmlSegmentRequest($job, $segments, $batchSegmentIndexes, [
                    'batch_index' => $batchIndex,
                    'segment_count' => count($batchSegmentIndexes),
                    'html_parallelism' => $parallelism,
                    'html_request_type' => 'segment_retry_batch',
                ]);
            }

            $results = $this->gateway->translateDocumentsConcurrently(
                $requests,
                $parallelism,
                false,
                true,
            );

            $remainingSegments = [];

            foreach ($retryBatches as $batchIndex => $batchSegmentIndexes) {
                if (! isset($results[$batchIndex]['response']) || ! is_array($results[$batchIndex]['response'])) {
                    $remainingSegments = [...$remainingSegments, ...$batchSegmentIndexes];

                    continue;
                }

                $parsed = $this->parseHtmlSegmentRequestResult(
                    $job,
                    $nodes,
                    $segments,
                    $batchSegmentIndexes,
                    $results[$batchIndex]['response'],
                );

                $nodeTexts = [...$nodeTexts, ...$parsed['node_texts']];
                $glossaryHits = [...$glossaryHits, ...$parsed['glossary_hits']];
                $riskFlags = [...$riskFlags, ...$parsed['risk_flags']];
                $notes = [...$notes, ...$parsed['notes']];
                $providerModel ??= $parsed['provider_model'];
                $providerLatencyMs += $parsed['provider_latency_ms'];
                $retryCount += $parsed['retry_count'];
                $remainingSegments = [...$remainingSegments, ...$parsed['invalid_segments']];
            }

            $remainingSegments = array_values(array_unique($remainingSegments));
        }

        if ($remainingSegments !== []) {
            $requests = [];

            foreach ($remainingSegments as $segmentIndex) {
                $requests[$segmentIndex] = $this->makeHtmlSegmentRequest($job, $segments, [$segmentIndex], [
                    'segment_count' => 1,
                    'segment_index' => $segmentIndex,
                    'html_parallelism' => $parallelism,
                    'html_request_type' => 'segment_single',
                ]);
            }

            $results = $this->gateway->translateDocumentsConcurrently(
                $requests,
                $parallelism,
                false,
                true,
            );

            foreach ($remainingSegments as $segmentIndex) {
                if (! isset($results[$segmentIndex]['response']) || ! is_array($results[$segmentIndex]['response'])) {
                    $fallbackSegmentCount++;

                    continue;
                }

                $parsed = $this->parseHtmlSegmentRequestResult(
                    $job,
                    $nodes,
                    $segments,
                    [$segmentIndex],
                    $results[$segmentIndex]['response'],
                );

                if ($parsed['invalid_segments'] !== []) {
                    $fallbackSegmentCount++;

                    continue;
                }

                $nodeTexts = [...$nodeTexts, ...$parsed['node_texts']];
                $glossaryHits = [...$glossaryHits, ...$parsed['glossary_hits']];
                $riskFlags = [...$riskFlags, ...$parsed['risk_flags']];
                $notes = [...$notes, ...$parsed['notes']];
                $providerModel ??= $parsed['provider_model'];
                $providerLatencyMs += $parsed['provider_latency_ms'];
                $retryCount += $parsed['retry_count'];
            }
        }

        return [
            'node_texts' => $nodeTexts,
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
            'fallback_segment_count' => $fallbackSegmentCount,
            'provider_model' => $providerModel,
            'provider_latency_ms' => $providerLatencyMs,
            'retry_count' => $retryCount,
        ];
    }

    /**
     * 组装 OpenClaw 翻译业务载荷，参数：$document 文档字段，$constraints 额外约束。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<string, string>  $document
     * @param  array<string, mixed>  $constraints
     * @return array<string, mixed>
     */
    protected function buildTranslationPayload(TranslationJob $job, array $document, array $constraints = []): array
    {
        return [
            'task_type' => 'translation',
            'task_subtype' => $job->document_type ?: 'chemical_news',
            'input_document' => $document,
            'context' => [
                'source_lang' => $job->source_lang,
                'target_lang' => $job->target_lang,
                'glossary_entries' => [],
                'constraints' => array_merge([
                    'preserve_units' => true,
                    'preserve_entities' => true,
                ], $constraints),
            ],
            'output_schema_version' => 'v1',
        ];
    }

    /**
     * 将 HTML 文本节点切分为批次，参数：$nodes 预编译节点列表。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<int, array{core_text: string}>  $nodes
     * @return array<int, array<int, int>>
     */
    protected function chunkHtmlTextNodes(array $nodes): array
    {
        $batches = [];
        $buffer = [];
        $bufferLength = 0;

        foreach ($nodes as $index => $node) {
            $nodeLength = mb_strlen((string) ($node['core_text'] ?? ''));

            if ($nodeLength === 0) {
                continue;
            }

            if (! $this->shouldBatchHtmlNode($node)) {
                if ($buffer !== []) {
                    $batches[] = $buffer;
                    $buffer = [];
                    $bufferLength = 0;
                }

                $batches[] = [$index];

                continue;
            }

            $wouldOverflowBatch = $buffer !== []
                && (
                    $bufferLength + $nodeLength > self::ASYNC_HTML_BATCH_TEXT_LIMIT
                    || count($buffer) >= self::ASYNC_HTML_MAX_BATCH_NODES
                );

            if ($wouldOverflowBatch) {
                $batches[] = $buffer;
                $buffer = [];
                $bufferLength = 0;
            }

            $buffer[] = $index;
            $bufferLength += $nodeLength;
        }

        if ($buffer !== []) {
            $batches[] = $buffer;
        }

        return $batches;
    }

    /**
     * 批量翻译一组 HTML 文本节点，参数：$job 任务模型，$nodes 节点列表，$batch 节点索引批次。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<int, array{
     *      original: string,
     *      leading_whitespace: string,
     *      core_text: string,
     *      trailing_whitespace: string,
     *      entity_map: array<string, string>
     *  }>  $nodes
     * @param  array<int, int>  $batch
     * @return array{
     *     node_texts: array<int, string>,
     *     translated_text_nodes: int,
     *     fallback_text_nodes: int,
     *     glossary_hits: array<int, mixed>,
     *     risk_flags: array<int, mixed>,
     *     notes: array<int, mixed>
     * }
     */
    protected function translateHtmlNodeBatch(TranslationJob $job, array $nodes, array $batch): array
    {
        $textMap = [];

        foreach ($batch as $nodeIndex) {
            $textMap['node_'.$nodeIndex] = $nodes[$nodeIndex]['core_text'];
        }

        if ($textMap !== [] && count($batch) > 1) {
            try {
                $response = $this->translateTextMapLenient($job, $textMap);
                $translatedDocument = (array) ($response['translated_document'] ?? []);
                $translatedNodeTexts = [];
                $translatedTextNodes = 0;
                $fallbackTextNodes = 0;
                $invalidNodeIndexes = [];
                $glossaryHits = (array) ($response['glossary_hits'] ?? []);
                $riskFlags = (array) ($response['risk_flags'] ?? []);
                $notes = (array) ($response['notes'] ?? []);

                foreach ($textMap as $key => $unused) {
                    $nodeIndex = (int) str_replace('node_', '', $key);

                    if (! array_key_exists($key, $translatedDocument)) {
                        $invalidNodeIndexes[] = $nodeIndex;

                        continue;
                    }

                    $translatedValue = $this->normalizeTranslatedOrdinalPrefix(
                        $nodes[$nodeIndex]['core_text'],
                        (string) $translatedDocument[$key],
                        $job->target_lang,
                    );

                    if ($this->translatedTextContainsSourceResidue($translatedValue, $job->target_lang)) {
                        $invalidNodeIndexes[] = $nodeIndex;

                        continue;
                    }

                    $result = $this->htmlTextNodeTranslator->hydrateNodeText(
                        $nodes[$nodeIndex],
                        $translatedValue,
                    );

                    $translatedNodeTexts[$nodeIndex] = $result['text'];
                    $translatedTextNodes += $result['translated'] ? 1 : 0;
                    $fallbackTextNodes += $result['fallback'] ? 1 : 0;
                }

                if ($invalidNodeIndexes === []) {
                    return [
                        'node_texts' => $translatedNodeTexts,
                        'translated_text_nodes' => $translatedTextNodes,
                        'fallback_text_nodes' => $fallbackTextNodes,
                        'glossary_hits' => (array) ($response['glossary_hits'] ?? []),
                        'risk_flags' => (array) ($response['risk_flags'] ?? []),
                        'notes' => (array) ($response['notes'] ?? []),
                    ];
                }

                $retryNodeResults = $this->retryInvalidHtmlNodes($job, $nodes, $invalidNodeIndexes);
                $translatedNodeTexts = [...$translatedNodeTexts, ...$retryNodeResults['node_texts']];
                $translatedTextNodes += $retryNodeResults['translated_text_nodes'];
                $fallbackTextNodes += $retryNodeResults['fallback_text_nodes'];
                $this->mergeTranslationSignals($retryNodeResults, $glossaryHits, $riskFlags, $notes);

                return [
                    'node_texts' => $translatedNodeTexts,
                    'translated_text_nodes' => $translatedTextNodes,
                    'fallback_text_nodes' => $fallbackTextNodes,
                    'glossary_hits' => $glossaryHits,
                    'risk_flags' => $riskFlags,
                    'notes' => $notes,
                ];
            } catch (Throwable) {
                // Fall through to per-node translation so one bad node does not poison the whole HTML payload.
            }
        }

        $translatedNodeTexts = [];
        $translatedTextNodes = 0;
        $fallbackTextNodes = 0;
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];

        foreach ($batch as $nodeIndex) {
            $result = $this->translateHtmlNodeWithFallback($job, $nodes[$nodeIndex]);
            $translatedNodeTexts[$nodeIndex] = $result['text'];
            $translatedTextNodes += $result['translated'] ? 1 : 0;
            $fallbackTextNodes += $result['fallback'] ? 1 : 0;
            $this->mergeTranslationSignals($result, $glossaryHits, $riskFlags, $notes);
        }

        return [
            'node_texts' => $translatedNodeTexts,
            'translated_text_nodes' => $translatedTextNodes,
            'fallback_text_nodes' => $fallbackTextNodes,
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
        ];
    }

    /**
     * 对批量结果中仍残留源语言的节点做二次分组重试，参数：$job 任务模型，$nodes 节点列表，$invalidNodeIndexes 待重试节点索引。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<int, array{
     *      original: string,
     *      leading_whitespace: string,
     *      core_text: string,
     *      trailing_whitespace: string,
     *      entity_map: array<string, string>
     *  }>  $nodes
     * @param  array<int, int>  $invalidNodeIndexes
     * @return array{
     *     node_texts: array<int, string>,
     *     translated_text_nodes: int,
     *     fallback_text_nodes: int,
     *     glossary_hits: array<int, mixed>,
     *     risk_flags: array<int, mixed>,
     *     notes: array<int, mixed>
     * }
     */
    protected function retryInvalidHtmlNodes(TranslationJob $job, array $nodes, array $invalidNodeIndexes): array
    {
        $translatedNodeTexts = [];
        $translatedTextNodes = 0;
        $fallbackTextNodes = 0;
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $remainingInvalidNodeIndexes = $invalidNodeIndexes;

        if (count($invalidNodeIndexes) > 1) {
            try {
                $retryTextMap = [];

                foreach ($invalidNodeIndexes as $nodeIndex) {
                    $retryTextMap['node_'.$nodeIndex] = $nodes[$nodeIndex]['core_text'];
                }

                $retryResponse = $this->translateTextMapLenient($job, $retryTextMap);
                $translatedDocument = (array) ($retryResponse['translated_document'] ?? []);
                $glossaryHits = [...$glossaryHits, ...(array) ($retryResponse['glossary_hits'] ?? [])];
                $riskFlags = [...$riskFlags, ...(array) ($retryResponse['risk_flags'] ?? [])];
                $notes = [...$notes, ...(array) ($retryResponse['notes'] ?? [])];
                $remainingInvalidNodeIndexes = [];

                foreach ($retryTextMap as $key => $unused) {
                    $nodeIndex = (int) str_replace('node_', '', $key);

                    if (! array_key_exists($key, $translatedDocument)) {
                        $remainingInvalidNodeIndexes[] = $nodeIndex;

                        continue;
                    }

                    $translatedValue = $this->normalizeTranslatedOrdinalPrefix(
                        $nodes[$nodeIndex]['core_text'],
                        (string) $translatedDocument[$key],
                        $job->target_lang,
                    );

                    if ($this->translatedTextContainsSourceResidue($translatedValue, $job->target_lang)) {
                        $remainingInvalidNodeIndexes[] = $nodeIndex;

                        continue;
                    }

                    $result = $this->htmlTextNodeTranslator->hydrateNodeText(
                        $nodes[$nodeIndex],
                        $translatedValue,
                    );

                    $translatedNodeTexts[$nodeIndex] = $result['text'];
                    $translatedTextNodes += $result['translated'] ? 1 : 0;
                    $fallbackTextNodes += $result['fallback'] ? 1 : 0;
                }
            } catch (Throwable) {
                $remainingInvalidNodeIndexes = $invalidNodeIndexes;
            }
        }

        foreach ($remainingInvalidNodeIndexes as $nodeIndex) {
            $result = $this->translateHtmlNodeWithFallback($job, $nodes[$nodeIndex]);
            $translatedNodeTexts[$nodeIndex] = $result['text'];
            $translatedTextNodes += $result['translated'] ? 1 : 0;
            $fallbackTextNodes += $result['fallback'] ? 1 : 0;
            $this->mergeTranslationSignals($result, $glossaryHits, $riskFlags, $notes);
        }

        return [
            'node_texts' => $translatedNodeTexts,
            'translated_text_nodes' => $translatedTextNodes,
            'fallback_text_nodes' => $fallbackTextNodes,
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
        ];
    }

    /**
     * 翻译单个 HTML 文本节点，失败后重试一次，再失败则回退原文。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array{
     *      original: string,
     *      leading_whitespace: string,
     *      core_text: string,
     *      trailing_whitespace: string,
     *      entity_map: array<string, string>
     *  }  $node
     * @return array{text: string, translated: bool, fallback: bool, glossary_hits: array<int, mixed>, risk_flags: array<int, mixed>, notes: array<int, mixed>}
     */
    protected function translateHtmlNodeWithFallback(TranslationJob $job, array $node): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = $this->translateTextValue($job, $node['core_text']);
                $translatedValue = $this->normalizeTranslatedOrdinalPrefix(
                    $node['core_text'],
                    (string) data_get($response, 'translated_document.text', ''),
                    $job->target_lang,
                );
                $result = $this->htmlTextNodeTranslator->hydrateNodeText(
                    $node,
                    $translatedValue,
                );

                return array_merge($result, [
                    'glossary_hits' => (array) ($response['glossary_hits'] ?? []),
                    'risk_flags' => (array) ($response['risk_flags'] ?? []),
                    'notes' => (array) ($response['notes'] ?? []),
                ]);
            } catch (Throwable) {
                continue;
            }
        }

        return [
            'text' => $node['original'],
            'translated' => false,
            'fallback' => true,
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
        ];
    }

    /**
     * 批量翻译多个文本字段，参数：$job 任务模型，$textMap 字段到文本的映射。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<string, string>  $textMap
     * @return array<string, mixed>
     */
    protected function translateTextMap(TranslationJob $job, array $textMap): array
    {
        return $this->gateway->translate([
            ...$textMap,
            'source_lang' => $job->source_lang,
            'target_lang' => $job->target_lang,
        ], jobId: $job->id);
    }

    /**
     * 宽松翻译多个文本字段，保留结构合法的批量返回供字段级补救使用。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<string, string>  $textMap
     * @return array<string, mixed>
     */
    protected function translateTextMapLenient(TranslationJob $job, array $textMap): array
    {
        return $this->gateway->translateLenient([
            ...$textMap,
            'source_lang' => $job->source_lang,
            'target_lang' => $job->target_lang,
        ], jobId: $job->id);
    }

    /**
     * 判断 HTML 节点是否适合并入批量请求，参数：$node 节点信息。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array{core_text: string}  $node
     */
    protected function shouldBatchHtmlNode(array $node): bool
    {
        return mb_strlen((string) ($node['core_text'] ?? '')) <= self::ASYNC_HTML_BATCH_TEXT_LIMIT;
    }

    /**
     * 判断批量翻译字段是否仍残留源语言字符，参数：$translatedText 翻译结果，$targetLang 目标语言。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    protected function translatedTextContainsSourceResidue(string $translatedText, string $targetLang): bool
    {
        if (preg_match('/__HTML_ENTITY_\d+__/u', $translatedText) === 1) {
            return true;
        }

        $normalizedTargetLang = strtolower(trim($targetLang));

        if ($normalizedTargetLang !== 'en' && ! str_starts_with($normalizedTargetLang, 'en-')) {
            return false;
        }

        return preg_match('/\p{Han}/u', $translatedText) === 1;
    }

    /**
     * 按源文中的中文枚举前缀，归一化英文译文的序号词，参数：$sourceText 源文本，$translatedText 译文，$targetLang 目标语言。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    protected function normalizeTranslatedOrdinalPrefix(string $sourceText, string $translatedText, string $targetLang): string
    {
        $normalizedTargetLang = strtolower(trim($targetLang));

        if (($normalizedTargetLang !== 'en' && ! str_starts_with($normalizedTargetLang, 'en-')) || $translatedText === '') {
            return $translatedText;
        }

        $ordinalMap = [
            '一是' => 'First',
            '二是' => 'Second',
            '三是' => 'Third',
            '四是' => 'Fourth',
            '五是' => 'Fifth',
            '六是' => 'Sixth',
        ];

        foreach ($ordinalMap as $sourcePrefix => $englishOrdinal) {
            if (! str_starts_with(trim($sourceText), $sourcePrefix)) {
                continue;
            }

            return preg_replace(
                '/^\s*(First|Second|Third|Fourth|Fifth|Sixth)([,:，：]?\s*)/i',
                $englishOrdinal.', ',
                $translatedText,
                1,
            ) ?? $translatedText;
        }

        return $translatedText;
    }

    /**
     * 合并一次翻译响应里的术语、风险和说明信息，参数：$response 源响应，$glossaryHits/$riskFlags/$notes 目标数组引用。
     * @since 2026-04-03
     * @author zhouxufeng
     * @param  array<string, mixed>  $response
     * @param  array<int, mixed>  $glossaryHits
     * @param  array<int, mixed>  $riskFlags
     * @param  array<int, mixed>  $notes
     */
    protected function mergeTranslationSignals(array $response, array &$glossaryHits, array &$riskFlags, array &$notes): void
    {
        $glossaryHits = [...$glossaryHits, ...(array) ($response['glossary_hits'] ?? [])];
        $riskFlags = [...$riskFlags, ...(array) ($response['risk_flags'] ?? [])];
        $notes = [...$notes, ...(array) ($response['notes'] ?? [])];
    }

    protected function htmlBatchParallelism(): int
    {
        if ($this->gateway->activeProvider() === \App\Enums\TranslationProvider::GitHubModels) {
            return 1;
        }

        return max(1, $this->gateway->htmlParallelism());
    }

    protected function htmlSegmentBatchTextLimit(): int
    {
        if ($this->gateway->activeProvider() === \App\Enums\TranslationProvider::GitHubModels) {
            return max(1, (int) config('services.github_models.html_segment_batch_text_limit', 900));
        }

        return self::ASYNC_HTML_SEGMENT_BATCH_TEXT_LIMIT;
    }

    protected function htmlMaxBatchSegments(): int
    {
        if ($this->gateway->activeProvider() === \App\Enums\TranslationProvider::GitHubModels) {
            return max(1, (int) config('services.github_models.html_max_batch_segments', 6));
        }

        return self::ASYNC_HTML_MAX_BATCH_SEGMENTS;
    }

    protected function htmlRetryBatchTextLimit(): int
    {
        if ($this->gateway->activeProvider() === \App\Enums\TranslationProvider::GitHubModels) {
            return max(1, (int) config('services.github_models.html_retry_batch_text_limit', 450));
        }

        return self::ASYNC_HTML_RETRY_BATCH_TEXT_LIMIT;
    }

    protected function htmlRetryMaxBatchSegments(): int
    {
        if ($this->gateway->activeProvider() === \App\Enums\TranslationProvider::GitHubModels) {
            return max(1, (int) config('services.github_models.html_retry_max_batch_segments', 3));
        }

        return self::ASYNC_HTML_RETRY_MAX_BATCH_SEGMENTS;
    }

    protected function assertWithinAsyncJobBudget(
        TranslationJob $job,
        int $estimatedRequests,
        int $parallelism,
        string $dispatchMode,
    ): void {
        $estimatedRequests = max(1, $estimatedRequests);
        $parallelism = max(1, $parallelism);
        $budgetSeconds = (int) floor(self::ASYNC_JOB_TIMEOUT_SECONDS * self::ASYNC_JOB_TIMEOUT_GUARD_RATIO);
        $estimatedRuntimeSeconds = (int) ceil($estimatedRequests / $parallelism) * max(1, $this->gateway->timeout());

        if ($estimatedRuntimeSeconds < $budgetSeconds) {
            return;
        }

        throw new RuntimeException(sprintf(
            'job_budget_exceeded: estimated provider runtime [%ds] exceeds the async worker budget [%ds] for provider [%s] using [%s].',
            $estimatedRuntimeSeconds,
            $budgetSeconds,
            $this->gateway->activeProvider()->value,
            $dispatchMode,
        ));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function metaInt(array $response, string $key): int
    {
        return max(0, (int) data_get($response, 'meta.'.$key, 0));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function metaString(array $response, string $key, string $default): string
    {
        $value = data_get($response, 'meta.'.$key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * 将长文本按段落与长度限制拆分为多个片段，参数：$text 原始文本。
     * @since 2026-04-02
     * @author zhouxufeng
     * @return array<int, string>
     */
    protected function splitPlainTextIntoChunks(string $text): array
    {
        $paragraphs = preg_split("/\n\s*\n/u", trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($paragraphs === []) {
            return [$text];
        }

        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $normalizedParagraph = trim($paragraph);

            if ($normalizedParagraph === '') {
                continue;
            }

            if (mb_strlen($normalizedParagraph) > self::ASYNC_TEXT_CHUNK_SIZE) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                foreach ($this->splitOversizedParagraph($normalizedParagraph) as $segment) {
                    $chunks[] = $segment;
                }

                continue;
            }

            $candidate = $buffer === '' ? $normalizedParagraph : $buffer."\n\n".$normalizedParagraph;

            if (mb_strlen($candidate) <= self::ASYNC_TEXT_CHUNK_SIZE) {
                $buffer = $candidate;
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            $buffer = $normalizedParagraph;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks === [] ? [$text] : $chunks;
    }

    /**
     * 拆分超长段落为更小片段，参数：$paragraph 超长段落文本。
     * @since 2026-04-02
     * @author zhouxufeng
     * @return array<int, string>
     */
    protected function splitOversizedParagraph(string $paragraph): array
    {
        $segments = preg_split('/(?<=[。！？!?；;])\s*/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($segments === []) {
            $segments = [$paragraph];
        }

        $chunks = [];
        $buffer = '';

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            if (mb_strlen($segment) > self::ASYNC_TEXT_CHUNK_SIZE) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }

                $offset = 0;
                $length = mb_strlen($segment);

                while ($offset < $length) {
                    $chunks[] = mb_substr($segment, $offset, self::ASYNC_TEXT_CHUNK_SIZE);
                    $offset += self::ASYNC_TEXT_CHUNK_SIZE;
                }

                continue;
            }

            $candidate = $buffer === '' ? $segment : $buffer.$segment;

            if (mb_strlen($candidate) <= self::ASYNC_TEXT_CHUNK_SIZE) {
                $buffer = $candidate;
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            $buffer = $segment;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    /**
     * 翻译单个文本值，必要时按长度切分后顺序拼接，参数：$job 任务模型，$text 文本内容。
     * @since 2026-04-03
     * @author zhouxufeng
     * @return array<string, mixed>
     */
    protected function translateTextValue(TranslationJob $job, string $text): array
    {
        $chunks = mb_strlen($text) > self::ASYNC_TEXT_CHUNK_SIZE
            ? $this->splitOversizedParagraph($text)
            : [$text];
        $this->assertWithinAsyncJobBudget($job, count($chunks), 1, 'sequential_text_chunks');

        $translatedChunks = [];
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $providerLatencyMs = 0;
        $retryCount = 0;
        $meta = [
            'schema_version' => 'v1',
            'provider_model' => $this->gateway->activeAgent(),
            'provider' => $this->gateway->activeProvider()->value,
            'segment_count' => count($chunks),
            'provider_dispatch_mode' => count($chunks) > 1 ? 'sequential_text_chunks' : 'single',
        ];

        foreach ($chunks as $chunk) {
            $response = $this->gateway->translate([
                'text' => $chunk,
                'source_lang' => $job->source_lang,
                'target_lang' => $job->target_lang,
            ], jobId: $job->id);

            $translatedChunks[] = (string) data_get($response, 'translated_document.text', '');
            $glossaryHits = [...$glossaryHits, ...(array) ($response['glossary_hits'] ?? [])];
            $riskFlags = [...$riskFlags, ...(array) ($response['risk_flags'] ?? [])];
            $notes = [...$notes, ...(array) ($response['notes'] ?? [])];
            $providerLatencyMs += $this->metaInt($response, 'provider_latency_ms');
            $retryCount += $this->metaInt($response, 'retry_count');
            $meta = array_merge($meta, array_filter((array) ($response['meta'] ?? []), static fn ($value, $key): bool => $key !== 'schema_version', ARRAY_FILTER_USE_BOTH));
        }

        $meta['provider_latency_ms'] = $providerLatencyMs;
        $meta['retry_count'] = $retryCount;
        $meta['segment_count'] = count($chunks);
        $meta['provider_dispatch_mode'] = count($chunks) > 1 ? 'sequential_text_chunks' : 'single';

        return [
            'translated_document' => [
                'text' => implode('', $translatedChunks),
            ],
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
            'meta' => $meta,
        ];
    }
}
