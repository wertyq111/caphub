<?php

namespace App\Jobs;

use App\Services\TaskCenter\TranslationJobService;
use App\Services\Translation\GlossaryHitPersister;
use App\Models\TranslationJob;
use App\Models\TranslationResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class FinalizeTranslationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public int $jobId,
        public array $result,
    ) {
        $this->onConnection('database');
    }

    public function handle(
        TranslationJobService $translationJobService,
        GlossaryHitPersister $glossaryHitPersister,
    ): void {
        $job = TranslationJob::query()->findOrFail($this->jobId);
        $response = (array) ($this->result['response'] ?? []);

        TranslationResult::query()->updateOrCreate(
            ['translation_job_id' => $job->id],
            [
                'translated_document_json' => (array) ($response['translated_document'] ?? []),
                'risk_payload' => (array) ($response['risk_flags'] ?? []),
                'notes_payload' => (array) ($response['notes'] ?? []),
                'meta_payload' => array_merge((array) ($response['meta'] ?? []), [
                    'mode' => 'async',
                    'glossary_hits' => (array) ($response['glossary_hits'] ?? []),
                ]),
            ],
        );

        $glossaryHitPersister->persistForJob(
            $job,
            (array) ($response['glossary_hits'] ?? []),
        );

        $translationJobService->markSucceeded($job);
    }

    public function failed(Throwable $throwable): void
    {
        $job = TranslationJob::query()->find($this->jobId);

        if ($job) {
            app(TranslationJobService::class)->markFailed($job, $throwable->getMessage());
        }
    }
}
