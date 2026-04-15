<?php

namespace App\Services\Translation;

use App\Models\TranslationJob;

class AsyncTranslationResultHealthValidator
{
    /**
     * 校验异步翻译结果是否可信，并补齐统一健康元数据。
     *
     * @param  array<string, mixed>  $response
     * @return array{response: array<string, mixed>, failure_reason: string|null}
     */
    public function validate(TranslationJob $job, array $response): array
    {
        $translatedDocument = (array) ($response['translated_document'] ?? []);
        $meta = (array) ($response['meta'] ?? []);
        $groups = $this->fallbackGroups($job, $translatedDocument, $meta);
        $fallbackSummary = $this->summarizeFallbackGroups($groups);

        $response['meta'] = array_merge($meta, [
            'full_fallback' => $fallbackSummary['full_fallback'],
            'fallback_ratio' => $fallbackSummary['fallback_ratio'],
            'retry_count' => $this->normalizeInt($meta['retry_count'] ?? 0),
            'provider_latency_ms' => $this->normalizeInt($meta['provider_latency_ms'] ?? 0),
            'segment_count' => $this->normalizeInt($meta['segment_count'] ?? 0),
            'provider_dispatch_mode' => $this->normalizeDispatchMode($meta['provider_dispatch_mode'] ?? null),
        ]);

        $failureReason = null;

        if ($fallbackSummary['full_fallback']) {
            $failureReason = 'full_fallback: translated output fell back to the source content for all available HTML text nodes.';
        } elseif ($groups === [] && $this->expectsEnglishOutput($job->target_lang) && $this->containsChineseCharacters($translatedDocument)) {
            $failureReason = 'full_fallback: translated output still contains Chinese characters for English target output.';
        }

        return [
            'response' => $response,
            'failure_reason' => $failureReason,
        ];
    }

    /**
     * @param  array<string, string>  $translatedDocument
     * @param  array<string, mixed>  $meta
     * @return array<int, array{translated: int, fallback: int, identical_to_source: bool}>
     */
    protected function fallbackGroups(TranslationJob $job, array $translatedDocument, array $meta): array
    {
        $groups = [];
        $sourceDocument = $this->sourceDocument($job);

        if (array_key_exists('translated_text_nodes', $meta) || array_key_exists('fallback_text_nodes', $meta)) {
            $groups[] = [
                'translated' => $this->normalizeInt($meta['translated_text_nodes'] ?? 0),
                'fallback' => $this->normalizeInt($meta['fallback_text_nodes'] ?? 0),
                'identical_to_source' => $this->matchesSourceText(
                    $translatedDocument['text'] ?? null,
                    $sourceDocument['text'] ?? null,
                ),
            ];
        }

        foreach ($translatedDocument as $field => $value) {
            if (! is_string($value)) {
                continue;
            }

            $translatedKey = $field.'_translated_text_nodes';
            $fallbackKey = $field.'_fallback_text_nodes';

            if (! array_key_exists($translatedKey, $meta) && ! array_key_exists($fallbackKey, $meta)) {
                continue;
            }

            $groups[] = [
                'translated' => $this->normalizeInt($meta[$translatedKey] ?? 0),
                'fallback' => $this->normalizeInt($meta[$fallbackKey] ?? 0),
                'identical_to_source' => $this->matchesSourceText(
                    $value,
                    $sourceDocument[$field] ?? null,
                ),
            ];
        }

        return $groups;
    }

    /**
     * @param  array<int, array{translated: int, fallback: int, identical_to_source: bool}>  $groups
     * @return array{full_fallback: bool, fallback_ratio: float}
     */
    protected function summarizeFallbackGroups(array $groups): array
    {
        $fullFallback = false;
        $translatedCount = 0;
        $fallbackCount = 0;

        foreach ($groups as $group) {
            $translated = $group['translated'];
            $fallback = $group['fallback'];

            $translatedCount += $translated;
            $fallbackCount += $fallback;

            if ($translated === 0 && $fallback > 0) {
                $fullFallback = true;
            }

            if ($fallback > 0 && $group['identical_to_source']) {
                $fullFallback = true;
            }
        }

        $ratio = 0.0;
        $total = $translatedCount + $fallbackCount;

        if ($total > 0) {
            $ratio = round($fallbackCount / $total, 4);
        }

        return [
            'full_fallback' => $fullFallback,
            'fallback_ratio' => $ratio,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function sourceDocument(TranslationJob $job): array
    {
        return array_filter([
            'text' => $job->source_text,
            'title' => $job->source_title,
            'summary' => $job->source_summary,
            'body' => $job->source_body,
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    protected function matchesSourceText(mixed $translatedValue, mixed $sourceValue): bool
    {
        return is_string($translatedValue)
            && is_string($sourceValue)
            && $translatedValue === $sourceValue;
    }

    /**
     * @param  array<string, string>  $translatedDocument
     */
    protected function containsChineseCharacters(array $translatedDocument): bool
    {
        foreach ($translatedDocument as $value) {
            if (! is_string($value)) {
                continue;
            }

            if (preg_match('/\p{Han}/u', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    protected function expectsEnglishOutput(string $targetLang): bool
    {
        $normalized = strtolower(trim($targetLang));

        return $normalized === 'en' || str_starts_with($normalized, 'en-');
    }

    protected function normalizeInt(mixed $value): int
    {
        return max(0, (int) $value);
    }

    protected function normalizeDispatchMode(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : 'single';
    }
}
