<?php

namespace App\Clients\Ai\OpenClaw;

use RuntimeException;
use Throwable;

class OpenClawTranslationGateway
{
    /**
     * 初始化 OpenClaw 翻译网关依赖，参数：客户端、载荷构建器、调用日志器。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected OpenClawClient $client,
        protected TranslationAgentPayloadBuilder $payloadBuilder,
        protected AiInvocationLogger $logger,
    ) {}

    /**
     * 调用 OpenClaw 执行翻译并记录调用日志，参数：输入文档、术语项、约束与任务 ID。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function translate(array $inputDocument, array $glossaryEntries = [], array $constraints = [], ?int $jobId = null): array
    {
        $payload = $this->payloadBuilder->build($inputDocument, $glossaryEntries, $constraints);
        return $this->translatePayload($payload, $jobId);
    }

    /**
     * 调用 OpenClaw 执行宽松翻译并记录调用日志，参数：输入文档、术语项、约束与任务 ID。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    public function translateLenient(array $inputDocument, array $glossaryEntries = [], array $constraints = [], ?int $jobId = null): array
    {
        $payload = $this->payloadBuilder->build($inputDocument, $glossaryEntries, $constraints);
        return $this->translatePayload($payload, $jobId, false, true);
    }

    /**
     * 直接发送已构建好的翻译载荷并记录调用日志，参数：$payload 标准 OpenClaw 载荷。
     * @since 2026-04-03
     * @author zhouxufeng
     */
    public function translatePayload(
        array $payload,
        ?int $jobId = null,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array
    {
        $startedAt = microtime(true);

        try {
            $response = $this->decorateResponse(
                $this->client->sendTranslationPayload(
                    $payload,
                    $enforceTargetLanguage,
                    $allowPartialTranslatedDocument,
                ),
                $this->durationInMs($startedAt),
                'single',
            );
            $this->safeLogTranslation(
                agentName: $this->translationAgent(),
                requestPayload: $payload,
                responsePayload: $response,
                status: 'success',
                durationMs: $this->durationInMs($startedAt),
                jobId: $jobId,
                context: [
                    'provider' => 'openclaw',
                ],
            );

            return $response;
        } catch (Throwable $throwable) {
            $this->safeLogTranslation(
                agentName: $this->translationAgent(),
                requestPayload: $payload,
                responsePayload: null,
                status: 'failed',
                durationMs: $this->durationInMs($startedAt),
                jobId: $jobId,
                errorMessage: $throwable->getMessage(),
                context: [
                    'provider' => 'openclaw',
                ],
            );

            throw $throwable;
        }
    }

    /**
     * 并发发送多组翻译文档并分别记录调用日志，参数：$requests 请求列表。
     * @since 2026-04-10
     * @author zhouxufeng
     * @param  array<array-key, array{
     *     payload: array<string, mixed>,
     *     job_id?: int|null,
     *     context?: array<string, mixed>,
     *     enforce_target_language?: bool,
     *     allow_partial_translated_document?: bool
     * }>  $requests
     * @return array<array-key, array{response?: array<string, mixed>, exception?: RuntimeException}>
     */
    public function translateDocumentsConcurrently(
        array $requests,
        int $concurrency = 4,
        bool $enforceTargetLanguage = true,
        bool $allowPartialTranslatedDocument = false,
    ): array {
        if ($requests === []) {
            return [];
        }

        $startedAt = [];
        $clientRequests = [];

        foreach ($requests as $key => $request) {
            $startedAt[$key] = microtime(true);
            $clientRequests[$key] = [
                'payload' => (array) ($request['payload'] ?? []),
                'enforce_target_language' => (bool) ($request['enforce_target_language'] ?? $enforceTargetLanguage),
                'allow_partial_translated_document' => (bool) ($request['allow_partial_translated_document'] ?? $allowPartialTranslatedDocument),
            ];
        }

        $results = $this->client->sendTranslationPayloadsConcurrently($clientRequests, $concurrency);

        foreach ($results as $key => $result) {
            $request = $requests[$key] ?? [];
            $payload = (array) ($request['payload'] ?? []);
            $jobId = array_key_exists('job_id', $request) ? $request['job_id'] : null;
            $context = (array) ($request['context'] ?? []);
            $dispatchMode = $concurrency > 1 ? 'bounded_concurrent' : 'single';

            if (isset($result['response']) && is_array($result['response'])) {
                $result['response'] = $this->decorateResponse(
                    $result['response'],
                    $this->durationInMs($startedAt[$key] ?? microtime(true)),
                    $dispatchMode,
                );
                $results[$key] = $result;
                $this->safeLogTranslation(
                    agentName: $this->translationAgent(),
                    requestPayload: $payload,
                    responsePayload: $result['response'],
                    status: 'success',
                    durationMs: $this->durationInMs($startedAt[$key] ?? microtime(true)),
                    jobId: $jobId,
                    context: array_merge($context, [
                        'provider' => 'openclaw',
                    ]),
                );

                continue;
            }

            $exception = $result['exception'] ?? new RuntimeException('OpenClaw concurrent translation request failed.');

            $this->safeLogTranslation(
                agentName: $this->translationAgent(),
                requestPayload: $payload,
                responsePayload: null,
                status: 'failed',
                durationMs: $this->durationInMs($startedAt[$key] ?? microtime(true)),
                jobId: $jobId,
                errorMessage: $exception instanceof Throwable ? $exception->getMessage() : (string) $exception,
                context: array_merge($context, [
                    'provider' => 'openclaw',
                ]),
            );
        }

        return $results;
    }

    public function htmlParallelism(): int
    {
        return max(1, (int) config('services.openclaw.html_parallelism', 2));
    }

    /**
     * 获取当前翻译 Agent 标识，参数：无。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function translationAgent(): string
    {
        return (string) config('services.openclaw.translation_agent', 'github-copilot/gpt-5-mini');
    }

    public function timeout(): int
    {
        return max(1, (int) config('services.openclaw.timeout', 45));
    }

    /**
     * 计算调用耗时毫秒值，参数：$startedAt 调用起始时间戳。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function durationInMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    protected function decorateResponse(array $response, int $durationMs, string $dispatchMode): array
    {
        $response['meta'] = array_merge((array) ($response['meta'] ?? []), [
            'provider_latency_ms' => max(0, $durationMs),
            'provider_dispatch_mode' => $dispatchMode,
        ]);

        return $response;
    }

    /**
     * 安全写入调用日志，参数：日志必要字段与可选错误信息。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function safeLogTranslation(
        string $agentName,
        array $requestPayload,
        ?array $responsePayload,
        string $status,
        int $durationMs,
        ?int $jobId = null,
        ?string $errorMessage = null,
        ?array $context = null,
    ): void {
        try {
            $this->logger->logTranslation(
                agentName: $agentName,
                requestPayload: $requestPayload,
                responsePayload: $responsePayload,
                status: $status,
                durationMs: $durationMs,
                jobId: $jobId,
                errorMessage: $errorMessage,
                context: $context,
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
