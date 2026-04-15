<?php

namespace App\Services\TaskCenter;

use App\Enums\TranslationJobStatus;
use App\Models\TranslationJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TranslationJobService
{
    public function createPendingAsyncJob(array $payload): TranslationJob
    {
        $content = $payload['content'] ?? [];

        return TranslationJob::query()->create([
            'job_uuid' => (string) Str::uuid(),
            'mode' => 'async',
            'status' => TranslationJobStatus::Pending,
            'failure_reason' => null,
            'input_type' => $payload['input_type'],
            'document_type' => $payload['document_type'] ?? null,
            'source_lang' => $payload['source_lang'],
            'target_lang' => $payload['target_lang'],
            'source_text' => $content['text'] ?? null,
            'source_title' => $content['title'] ?? null,
            'source_summary' => $content['summary'] ?? null,
            'source_body' => $content['body'] ?? null,
        ]);
    }

    public function findByUuid(string $jobUuid): ?TranslationJob
    {
        return TranslationJob::query()->where('job_uuid', $jobUuid)->first();
    }

    public function markProcessing(TranslationJob $job): TranslationJob
    {
        $job->forceFill([
            'status' => TranslationJobStatus::Processing,
            'failure_reason' => null,
            'started_at' => $job->started_at ?? now(),
        ])->save();

        return $job->refresh();
    }

    public function markSucceeded(TranslationJob $job, ?Carbon $finishedAt = null): TranslationJob
    {
        $job->forceFill([
            'status' => TranslationJobStatus::Succeeded,
            'failure_reason' => null,
            'finished_at' => $finishedAt ?? now(),
        ])->save();

        return $job->refresh();
    }

    public function markFailed(
        TranslationJob $job,
        ?string $failureReason = null,
        ?Carbon $finishedAt = null,
    ): TranslationJob
    {
        $job->forceFill([
            'status' => TranslationJobStatus::Failed,
            'failure_reason' => $this->normalizeFailureReason($failureReason),
            'finished_at' => $finishedAt ?? now(),
        ])->save();

        return $job->refresh();
    }

    protected function normalizeFailureReason(?string $failureReason): ?string
    {
        $normalized = trim((string) $failureReason);

        if ($normalized === '') {
            return null;
        }

        foreach (['upstream_timeout:', 'full_fallback:', 'job_budget_exceeded:'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return $normalized;
            }
        }

        if ($this->isUpstreamTimeoutReason($normalized)) {
            return 'upstream_timeout: '.$normalized;
        }

        if (str_contains($normalized, 'contains Chinese characters for English target output')) {
            return 'full_fallback: '.$normalized;
        }

        return $normalized;
    }

    protected function isUpstreamTimeoutReason(string $failureReason): bool
    {
        $normalized = strtolower($failureReason);

        return str_contains($normalized, 'curl error 28')
            || str_contains($normalized, 'timed out')
            || str_contains($normalized, 'status code 429')
            || str_contains($normalized, 'status code 502')
            || str_contains($normalized, 'status code 503')
            || str_contains($normalized, 'status code 504');
    }
}
