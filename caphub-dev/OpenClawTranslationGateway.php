<?php

namespace App\Clients\Ai\OpenClaw;

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
            $response = $this->client->sendTranslationPayload(
                $payload,
                $enforceTargetLanguage,
                $allowPartialTranslatedDocument,
            );
            $this->safeLogTranslation(
                agentName: $this->translationAgent(),
                requestPayload: $payload,
                responsePayload: $response,
                status: 'success',
                durationMs: $this->durationInMs($startedAt),
                jobId: $jobId,
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
            );

            throw $throwable;
        }
    }

    /**
     * 获取当前翻译 Agent 标识，参数：无。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function translationAgent(): string
    {
        return (string) config('services.openclaw.translation_agent', 'chemical-news-translator');
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
            );
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
