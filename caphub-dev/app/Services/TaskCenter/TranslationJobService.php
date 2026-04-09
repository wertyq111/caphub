<?php

namespace App\Services\TaskCenter;

use App\Enums\TranslationJobStatus;
use App\Models\TranslationJob;
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

    public function markSucceeded(TranslationJob $job): TranslationJob
    {
        $job->forceFill([
            'status' => TranslationJobStatus::Succeeded,
            'failure_reason' => null,
            'finished_at' => now(),
        ])->save();

        return $job->refresh();
    }

    public function markFailed(TranslationJob $job, ?string $failureReason = null): TranslationJob
    {
        $job->forceFill([
            'status' => TranslationJobStatus::Failed,
            'failure_reason' => $failureReason,
            'finished_at' => now(),
        ])->save();

        return $job->refresh();
    }
}
