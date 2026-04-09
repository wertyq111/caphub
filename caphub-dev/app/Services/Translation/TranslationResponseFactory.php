<?php

namespace App\Services\Translation;

class TranslationResponseFactory
{
    /**
     * 将同步翻译结果转换为接口响应结构，参数：$result 领域层结果。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function fromSyncResult(array $result): array
    {
        $response = (array) ($result['response'] ?? []);
        $meta = (array) ($response['meta'] ?? []);

        return [
            'status' => 'succeeded',
            'input_type' => $result['input_type'] ?? 'plain_text',
            'translated_document' => (array) ($response['translated_document'] ?? []),
            'glossary_hits' => (array) ($response['glossary_hits'] ?? []),
            'risk_flags' => (array) ($response['risk_flags'] ?? []),
            'notes' => (array) ($response['notes'] ?? []),
            'meta' => array_merge($meta, [
                'mode' => $result['mode'] ?? 'sync',
            ]),
        ];
    }
}
