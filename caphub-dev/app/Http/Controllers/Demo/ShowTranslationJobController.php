<?php

namespace App\Http\Controllers\Demo;

use App\Enums\TranslationJobStatus;
use App\Services\TaskCenter\TranslationJobService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ShowTranslationJobController extends Controller
{
    /**
     * 初始化任务查询控制器依赖，参数：$translationJobService 任务服务。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected TranslationJobService $translationJobService,
    ) {}

    /**
     * 查询翻译任务状态，参数：$jobUuid 任务 UUID。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __invoke(string $jobUuid): JsonResponse
    {
        $job = $this->translationJobService->findByUuid($jobUuid);

        if (! $job) {
            return response()->json([
                'message' => 'Translation job not found.',
                'error' => [
                    'code' => 'translation_job_not_found',
                    'reason' => 'No async translation job exists for the provided UUID.',
                ],
            ], 404);
        }

        $payload = [
            'job_id' => $job->id,
            'job_uuid' => $job->job_uuid,
            'status' => $job->status->value,
            'input_type' => $job->input_type,
            'document_type' => $job->document_type,
            'source_lang' => $job->source_lang,
            'target_lang' => $job->target_lang,
            'started_at' => optional($job->started_at)?->toIso8601String(),
            'finished_at' => optional($job->finished_at)?->toIso8601String(),
            'source_document' => $this->sourceDocument($job),
            'translated_document' => $job->result?->translated_document_json ?? [],
        ];

        if ($job->status === TranslationJobStatus::Failed) {
            $payload['error'] = [
                'code' => 'translation_failed',
                'reason' => $job->failure_reason ?: 'The async translation job failed without a recorded reason.',
            ];
        }

        return response()->json($payload);
    }

    /**
     * @return array<string, string>
     */
    protected function sourceDocument(\App\Models\TranslationJob $job): array
    {
        if ($job->input_type === 'plain_text') {
            return array_filter([
                'text' => (string) ($job->source_text ?? ''),
            ], static fn (string $value): bool => $value !== '');
        }

        return array_filter([
            'title' => (string) ($job->source_title ?? ''),
            'summary' => (string) ($job->source_summary ?? ''),
            'body' => (string) ($job->source_body ?? ''),
        ], static fn (string $value): bool => $value !== '');
    }
}
