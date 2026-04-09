<?php

namespace App\Http\Controllers\Demo;

use App\Services\Translation\TranslationRequestNormalizer;
use App\Services\Translation\TranslationResponseFactory;
use App\Services\Translation\TranslationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Demo\StoreSyncTranslationRequest;
use Illuminate\Http\JsonResponse;
use Throwable;

class SyncTranslationController extends Controller
{
    /**
     * 初始化同步翻译控制器依赖，参数：标准化器、翻译服务、响应工厂。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __construct(
        protected TranslationRequestNormalizer $normalizer,
        protected TranslationService $translationService,
        protected TranslationResponseFactory $responseFactory,
    ) {}

    /**
     * 执行同步翻译接口，参数：$request 同步翻译请求。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function __invoke(StoreSyncTranslationRequest $request): JsonResponse
    {
        try {
            $normalized = $this->normalizer->normalize($request->validated());
            $result = $this->translationService->translateSync($normalized);

            return response()->json(
                $this->responseFactory->fromSyncResult($result),
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Translation failed.',
            ], 502);
        }
    }
}
