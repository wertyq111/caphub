<?php

namespace App\Http\Controllers\Demo;

use App\Enums\TranslationJobStatus;
use App\Services\TaskCenter\TranslationJobService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ShowTranslationResultController extends Controller
{
    /**
     * 初始化翻译结果查询控制器依赖，参数：$translationJobService 任务服务。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected TranslationJobService $translationJobService,
    ) {}

    /**
     * 查询翻译结果详情，参数：$jobUuid 任务 UUID。
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

        if ($job->status === TranslationJobStatus::Failed) {
            return response()->json([
                'job_id' => $job->id,
                'job_uuid' => $job->job_uuid,
                'status' => $job->status->value,
                'input_type' => $job->input_type,
                'message' => 'Translation job failed.',
                'error' => [
                    'code' => 'translation_failed',
                    'reason' => $job->failure_reason ?: 'The async translation job failed without a recorded reason.',
                ],
            ], 409);
        }

        if (! $job->result) {
            return response()->json([
                'job_id' => $job->id,
                'job_uuid' => $job->job_uuid,
                'status' => $job->status->value,
                'input_type' => $job->input_type,
                'message' => 'Translation result is not ready yet.',
                'error' => [
                    'code' => 'translation_result_not_ready',
                    'reason' => 'The async translation job has not produced a result yet.',
                ],
            ], 202);
        }

        $result = $job->result;

        return response()->json([
            'job_id' => $job->id,
            'job_uuid' => $job->job_uuid,
            'status' => $job->status->value,
            'input_type' => $job->input_type,
            'translated_document' => $result->translated_document_json ?? [],
            'glossary_hits' => (array) (($result->meta_payload ?? [])['glossary_hits'] ?? []),
            'risk_flags' => $result->risk_payload ?? [],
            'notes' => $result->notes_payload ?? [],
            'meta' => array_merge(collect((array) ($result->meta_payload ?? []))
                ->except('glossary_hits')
                ->all(), [
                    'mode' => 'async',
                ]),
        ]);
    }
}
