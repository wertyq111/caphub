<?php

namespace App\Services\Translation;

use App\Models\TranslationJob;
use App\Models\TranslationResult;

class TranslationResultPersister
{
    /**
     * 持久化翻译结果快照，参数：$job 任务模型，$response 翻译响应，$cacheHit 是否命中缓存。
     * @since 2026-04-02
     * @author zhouxufeng
     * @param  array<string, mixed>  $response
     */
    public function persist(TranslationJob $job, array $response, bool $cacheHit): TranslationResult
    {
        return TranslationResult::query()->updateOrCreate(
            ['translation_job_id' => $job->id],
            [
                'translated_document_json' => (array) ($response['translated_document'] ?? []),
                'risk_payload' => (array) ($response['risk_flags'] ?? []),
                'notes_payload' => (array) ($response['notes'] ?? []),
                'meta_payload' => array_merge((array) ($response['meta'] ?? []), [
                    'mode' => $job->mode,
                    'cache_hit' => $cacheHit,
                    'glossary_hits' => (array) ($response['glossary_hits'] ?? []),
                ]),
            ],
        );
    }
}
