<?php

namespace App\Jobs;

use App\Services\TaskCenter\TranslationJobService;
use App\Services\Translation\TranslationService;
use App\Models\TranslationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessTranslationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public int $jobId,
    ) {
        $this->onConnection('database');
    }

    public function handle(
        TranslationJobService $translationJobService,
        TranslationService $translationService,
    ): void {
        $job = TranslationJob::query()->findOrFail($this->jobId);
        $translationJobService->markProcessing($job);
        $result = $translationService->translateAsyncJob($job->id);

        FinalizeTranslationJob::dispatch($job->id, $result)->onConnection('database');
    }

    public function failed(Throwable $throwable): void
    {
        $job = TranslationJob::query()->find($this->jobId);

        if ($job) {
            app(TranslationJobService::class)->markFailed($job, $throwable->getMessage());
        }
    }
}
