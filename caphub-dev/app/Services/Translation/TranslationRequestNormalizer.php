<?php

namespace App\Services\Translation;

use Illuminate\Support\Arr;

class TranslationRequestNormalizer
{
    /**
     * 归一化翻译请求结构，参数：$payload 原始请求体。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $content = (array) Arr::get($payload, 'content', []);
        $inputType = (string) Arr::get($payload, 'input_type', 'plain_text');
        $sharedDocument = $this->sharedDocumentFor($inputType, $content);

        return [
            'input_type' => $inputType,
            'document_type' => Arr::get($payload, 'document_type'),
            'source_lang' => (string) Arr::get($payload, 'source_lang'),
            'target_lang' => (string) Arr::get($payload, 'target_lang'),
            'domain' => 'chemical_news',
            'mode' => 'sync',
            ...$sharedDocument,
            'input_document' => $this->inputDocumentFor($sharedDocument),
            'glossary_entries' => [],
            'constraints' => [
                'preserve_units' => true,
                'preserve_entities' => true,
            ],
            'openclaw_payload' => $this->openclawPayload(
                sourceLang: (string) Arr::get($payload, 'source_lang'),
                targetLang: (string) Arr::get($payload, 'target_lang'),
                inputDocument: $this->inputDocumentFor($sharedDocument),
            ),
        ];
    }

    /**
     * 基于输入类型提取统一文档字段，参数：$inputType 输入类型，$content 内容载荷。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $content
     * @return array<string, string|null>
     */
    protected function sharedDocumentFor(string $inputType, array $content): array
    {
        if ($inputType === 'article_payload') {
            return [
                'source_title' => $this->nullableString($content, 'title'),
                'source_summary' => $this->nullableString($content, 'summary'),
                'source_body' => $this->nullableString($content, 'body'),
                'source_text' => null,
            ];
        }

        return [
            'source_title' => null,
            'source_summary' => null,
            'source_body' => null,
            'source_text' => $this->nullableString($content, 'text'),
        ];
    }

    /**
     * 生成给 AI 的输入文档对象，参数：$sharedDocument 统一文档字段。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, string|null>  $sharedDocument
     * @return array<string, string>
     */
    protected function inputDocumentFor(array $sharedDocument): array
    {
        return array_filter([
            'text' => $sharedDocument['source_text'] ?? null,
            'title' => $sharedDocument['source_title'] ?? null,
            'summary' => $sharedDocument['source_summary'] ?? null,
            'body' => $sharedDocument['source_body'] ?? null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * 组装 OpenClaw 请求载荷，参数：源语言、目标语言与输入文档。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, string>  $inputDocument
     * @return array<string, mixed>
     */
    protected function openclawPayload(string $sourceLang, string $targetLang, array $inputDocument): array
    {
        return [
            'task_type' => 'translation',
            'task_subtype' => 'chemical_news',
            'input_document' => $inputDocument,
            'context' => [
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'glossary_entries' => [],
                'constraints' => [
                    'preserve_units' => true,
                    'preserve_entities' => true,
                ],
            ],
            'output_schema_version' => 'v1',
        ];
    }

    /**
     * 读取并规范化可空字符串，参数：$content 内容对象，$key 字段名。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $content
     */
    protected function nullableString(array $content, string $key): ?string
    {
        $value = trim((string) Arr::get($content, $key, ''));

        return $value === '' ? null : $value;
    }
}
