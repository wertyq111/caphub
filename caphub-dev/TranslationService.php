<?php

namespace App\Services\Translation;

use App\Enums\TranslationJobStatus;
use App\Clients\Ai\OpenClaw\OpenClawClient;
use App\Clients\Ai\OpenClaw\OpenClawTranslationGateway;
use App\Models\DemoAccessLog;
use App\Models\TranslationJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TranslationService
{
    protected const ASYNC_TEXT_CHUNK_THRESHOLD = 1800;

    protected const ASYNC_TEXT_CHUNK_SIZE = 1200;

    protected const ASYNC_HTML_BATCH_TEXT_LIMIT = 6000;

    protected const ASYNC_HTML_MAX_BATCH_NODES = 128;

    /**
     * 初始化翻译服务依赖，参数：客户端、模式解析、结果持久化与术语命中持久化服务。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected OpenClawClient $client,
        protected OpenClawTranslationGateway $gateway,
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
        $cacheKey = $this->syncCacheKey($normalizedRequest);
        $lockSeconds = $this->syncCacheLockSeconds();
        $waitSeconds = $this->syncCacheLockWaitSeconds();

        return Cache::lock($cacheKey.':lock', $lockSeconds)->block($waitSeconds, function () use ($cacheKey, $normalizedRequest) {
            $cachedResponse = Cache::get($cacheKey);

            if (is_array($cachedResponse)) {
                $this->recordDemoAccess('sync_translation_cache_hit');

                return $this->syncResultForResponse($normalizedRequest, $this->decorateResponse($cachedResponse, true));
            }

            $response = $this->gateway->translatePayload(
                (array) $normalizedRequest['openclaw_payload'],
                null,
            );

            $result = DB::transaction(function () use ($normalizedRequest, $response) {
                $job = $this->createSyncJob($normalizedRequest);

                $this->resultPersister->persist($job, $response, false);
                $this->glossaryHitPersister->persistForJob(
                    $job,
                    (array) ($response['glossary_hits'] ?? []),
                    $normalizedRequest['domain'] ?? null,
                );

                $this->recordDemoAccess('sync_translation_cache_miss', $job->id);

                return $this->syncResultForResponse($normalizedRequest, $this->decorateResponse($response, false));
            });

            Cache::put($cacheKey, $response, now()->addHour());

            return $result;
        });
    }

    /**
     * 计算同步翻译缓存锁时长，确保相同请求在上游超时窗口内不会重复穿透。
     * @since 2026-04-09
     * @author zhouxufeng
     */
    protected function syncCacheLockSeconds(): int
    {
        $upstreamTimeout = max(1, (int) config('services.openclaw.timeout', 30));

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
            'translation_agent' => config('services.openclaw.translation_agent', 'chemical-news-translator'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
     * 创建同步翻译任务记录，参数：$normalizedRequest 标准化请求。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $normalizedRequest
     */
    protected function createSyncJob(array $normalizedRequest): TranslationJob
    {
        $now = now();

        return TranslationJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'mode' => $this->modeResolver->resolve($normalizedRequest),
            'status' => TranslationJobStatus::Succeeded,
            'input_type' => $normalizedRequest['input_type'] ?? 'plain_text',
            'document_type' => $normalizedRequest['document_type'] ?? null,
            'source_lang' => $normalizedRequest['source_lang'] ?? '',
            'target_lang' => $normalizedRequest['target_lang'] ?? '',
            'source_text' => $normalizedRequest['source_text'] ?? null,
            'source_title' => $normalizedRequest['source_title'] ?? null,
            'source_summary' => $normalizedRequest['source_summary'] ?? null,
            'source_body' => $normalizedRequest['source_body'] ?? null,
            'started_at' => $now,
            'finished_at' => $now,
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
        $translatedChunks = [];
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $meta = [
            'schema_version' => 'v1',
            'provider_model' => config('services.openclaw.translation_agent'),
            'chunked' => true,
            'chunk_count' => count($chunks),
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
            $meta = array_merge($meta, array_filter((array) ($response['meta'] ?? []), static fn ($value, $key): bool => $key !== 'schema_version', ARRAY_FILTER_USE_BOTH));
        }

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
        $meta = [
            'schema_version' => 'v1',
            'provider_model' => config('services.openclaw.translation_agent'),
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
            $meta = array_merge(
                $meta,
                array_filter((array) ($response['meta'] ?? []), static fn ($value, $key): bool => $key !== 'schema_version', ARRAY_FILTER_USE_BOTH),
            );
        }

        foreach ($htmlFields as $field) {
            $htmlTranslation = $this->translateAsyncHtmlContent($job, (string) $inputDocument[$field]);
            $translatedDocument[$field] = $htmlTranslation['text'];
            $this->mergeTranslationSignals($htmlTranslation, $glossaryHits, $riskFlags, $notes);

            foreach ((array) ($htmlTranslation['meta'] ?? []) as $metaKey => $metaValue) {
                if ($metaKey === 'schema_version' || $metaKey === 'provider_model') {
                    continue;
                }

                $meta[$field.'_'.$metaKey] = $metaValue;
            }
        }

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
        $compiled = $this->htmlTextNodeTranslator->compile($text);
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $translatedNodeTexts = [];
        $translatedTextNodes = 0;
        $fallbackTextNodes = 0;
        $batches = $this->chunkHtmlTextNodes($compiled['nodes']);

        foreach ($batches as $batch) {
            $translatedBatch = $this->translateHtmlNodeBatch($job, $compiled['nodes'], $batch);

            foreach ($translatedBatch['node_texts'] as $nodeIndex => $nodeText) {
                $translatedNodeTexts[$nodeIndex] = $nodeText;
            }

            $translatedTextNodes += $translatedBatch['translated_text_nodes'];
            $fallbackTextNodes += $translatedBatch['fallback_text_nodes'];
            $this->mergeTranslationSignals($translatedBatch, $glossaryHits, $riskFlags, $notes);
        }

        return [
            'text' => $this->htmlTextNodeTranslator->render($compiled['parts'], $translatedNodeTexts),
            'glossary_hits' => $glossaryHits,
            'risk_flags' => $riskFlags,
            'notes' => $notes,
            'meta' => [
                'schema_version' => 'v1',
                'provider_model' => config('services.openclaw.translation_agent'),
                'html_mode' => true,
                'translated_text_nodes' => $translatedTextNodes,
                'fallback_text_nodes' => $fallbackTextNodes,
                'html_batch_count' => count($batches),
            ],
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

        $translatedChunks = [];
        $glossaryHits = [];
        $riskFlags = [];
        $notes = [];
        $meta = [
            'schema_version' => 'v1',
            'provider_model' => config('services.openclaw.translation_agent'),
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
            $meta = array_merge($meta, array_filter((array) ($response['meta'] ?? []), static fn ($value, $key): bool => $key !== 'schema_version', ARRAY_FILTER_USE_BOTH));
        }

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
