<?php

namespace App\Services\Translation;

use App\Models\Glossary;
use App\Models\TranslationGlossaryHit;
use App\Models\TranslationJob;

class GlossaryHitPersister
{
    /**
     * 按任务持久化术语命中记录，参数：$job 任务模型，$glossaryHits 命中数据，$domain 术语域。
     * @since 2026-04-02
     * @author zhouxufeng
     * @return array<int, TranslationGlossaryHit>
     */
    public function persistForJob(TranslationJob $job, array $glossaryHits, ?string $domain = null): array
    {
        TranslationGlossaryHit::query()
            ->where('job_id', $job->id)
            ->delete();

        $persistedHits = [];

        foreach ($glossaryHits as $hit) {
            $sourceTerm = trim((string) ($hit['source_term'] ?? ''));
            $chosenTranslation = trim((string) ($hit['chosen_translation'] ?? ''));

            if ($sourceTerm === '' || $chosenTranslation === '') {
                continue;
            }

            $glossary = $this->resolveGlossary(
                sourceTerm: $sourceTerm,
                chosenTranslation: $chosenTranslation,
                sourceLang: $job->source_lang,
                targetLang: $job->target_lang,
                domain: $domain,
            );

            if (! $glossary) {
                continue;
            }

            $persistedHits[] = TranslationGlossaryHit::query()->create([
                'job_id' => $job->id,
                'glossary_id' => $glossary->id,
                'source_term' => $sourceTerm,
                'chosen_translation' => $chosenTranslation,
                'match_text' => (string) ($hit['match_text'] ?? $sourceTerm),
                'match_position' => is_array($hit['match_position'] ?? null) ? $hit['match_position'] : null,
                'hit_source' => (string) ($hit['hit_source'] ?? 'provider'),
            ]);
        }

        return $persistedHits;
    }

    /**
     * 根据命中内容解析术语主表记录，参数：源术语、目标译文、语言对与可选领域。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    protected function resolveGlossary(
        string $sourceTerm,
        string $chosenTranslation,
        string $sourceLang,
        string $targetLang,
        ?string $domain = null,
    ): ?Glossary {
        return Glossary::query()
            ->where('term', $sourceTerm)
            ->where('standard_translation', $chosenTranslation)
            ->where('source_lang', $sourceLang)
            ->where('target_lang', $targetLang)
            ->when($domain !== null, static function ($query) use ($domain): void {
                $query->where('domain', $domain);
            })
            ->first();
    }
}
