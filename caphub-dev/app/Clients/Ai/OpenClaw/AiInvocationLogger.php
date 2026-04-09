<?php

namespace App\Clients\Ai\OpenClaw;

use App\Models\AiInvocation;
use Illuminate\Support\Arr;

class AiInvocationLogger
{
    /**
     * 记录一次翻译调用日志，参数：Agent 名称、请求与响应、状态、耗时及扩展信息。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function logTranslation(
        string $agentName,
        array $requestPayload,
        ?array $responsePayload,
        string $status,
        int $durationMs,
        ?int $jobId = null,
        ?string $skillVersion = null,
        ?int $tokenUsageEstimate = null,
        ?string $errorMessage = null,
    ): AiInvocation {
        return AiInvocation::create([
            'job_id' => $jobId,
            'agent_name' => $agentName,
            'skill_version' => $skillVersion,
            'request_payload' => $this->summarizeRequest($requestPayload),
            'response_payload_summary' => $this->summarizeResponse($responsePayload),
            'status' => $status,
            'duration_ms' => $durationMs,
            'token_usage_estimate' => $tokenUsageEstimate,
            'error_message' => $errorMessage,
            'created_at' => now(),
        ]);
    }

    /**
     * 汇总请求体信息用于日志落库，参数：$requestPayload 原始请求数据。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function summarizeRequest(array $requestPayload): array
    {
        $document = (array) Arr::get($requestPayload, 'input_document', []);
        $glossaryEntries = (array) Arr::get($requestPayload, 'context.glossary_entries', []);

        return [
            'task_type' => Arr::get($requestPayload, 'task_type'),
            'task_subtype' => Arr::get($requestPayload, 'task_subtype'),
            'output_schema_version' => Arr::get($requestPayload, 'output_schema_version'),
            'document_keys' => array_keys($document),
            'document_lengths' => $this->summarizeDocumentLengths($document),
            'source_lang' => Arr::get($requestPayload, 'context.source_lang'),
            'target_lang' => Arr::get($requestPayload, 'context.target_lang'),
            'glossary_entries_count' => count($glossaryEntries),
            'constraints' => Arr::get($requestPayload, 'context.constraints', []),
        ];
    }

    /**
     * 汇总响应体信息用于日志落库，参数：$responsePayload 可空响应数据。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function summarizeResponse(?array $responsePayload): ?array
    {
        if ($responsePayload === null) {
            return null;
        }

        $translatedDocument = (array) Arr::get($responsePayload, 'translated_document', []);

        return [
            'translated_document_keys' => array_keys($translatedDocument),
            'translated_document_lengths' => $this->summarizeDocumentLengths($translatedDocument),
            'meta' => Arr::get($responsePayload, 'meta'),
            'glossary_hits_count' => count((array) Arr::get($responsePayload, 'glossary_hits', [])),
            'risk_flags_count' => count((array) Arr::get($responsePayload, 'risk_flags', [])),
            'notes_count' => count((array) Arr::get($responsePayload, 'notes', [])),
        ];
    }

    /**
     * 统计文档各字段字符长度，参数：$document 文档字段数组。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function summarizeDocumentLengths(array $document): array
    {
        return collect($document)
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): int => mb_strlen($value))
            ->all();
    }
}
