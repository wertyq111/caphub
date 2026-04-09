<?php

namespace App\Http\Controllers\Demo;

use App\Services\TaskCenter\TranslationJobService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Demo\StoreAsyncTranslationRequest;
use App\Jobs\ProcessTranslationJob;
use Illuminate\Http\JsonResponse;

class AsyncTranslationController extends Controller
{
    /**
     * 初始化异步翻译控制器依赖，参数：$translationJobService 异步任务服务。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected TranslationJobService $translationJobService,
    ) {}

    /**
     * 创建异步翻译任务并投递队列，参数：$request 异步翻译请求。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __invoke(StoreAsyncTranslationRequest $request): JsonResponse
    {
        $job = $this->translationJobService->createPendingAsyncJob($request->validated());

        ProcessTranslationJob::dispatch($job->id)->onConnection('database');

        return response()->json([
            'job_id' => $job->id,
            'job_uuid' => $job->job_uuid,
            'status' => $job->status->value,
        ], 202);
    }
}
