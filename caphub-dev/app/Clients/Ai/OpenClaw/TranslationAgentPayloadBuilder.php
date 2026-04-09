<?php

namespace App\Clients\Ai\OpenClaw;

use Illuminate\Support\Arr;

class TranslationAgentPayloadBuilder
{
    /**
     * 构建 OpenClaw 翻译请求载荷，参数：输入文档、术语表与约束条件。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function build(array $inputDocument, array $glossaryEntries = [], array $constraints = []): array
    {
        return [
            'task_type' => 'translation',
            'task_subtype' => 'chemical_news',
            'input_document' => $this->buildInputDocument($inputDocument),
            'context' => [
                'source_lang' => Arr::get($inputDocument, 'source_lang'),
                'target_lang' => Arr::get($inputDocument, 'target_lang'),
                'glossary_entries' => array_values($glossaryEntries),
                'constraints' => array_merge([
                    'preserve_units' => true,
                    'preserve_entities' => true,
                ], $constraints),
            ],
            'output_schema_version' => 'v1',
        ];
    }

    /**
     * 过滤输入文档字段，仅保留需要翻译的字符串值，参数：$inputDocument 原始输入数组。
     * @since 2026-04-03
     * @author zhouxufeng
     * @return array<string, string>
     */
    protected function buildInputDocument(array $inputDocument): array
    {
        return collect(Arr::except($inputDocument, ['source_lang', 'target_lang']))
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): string => $value)
            ->all();
    }
}
