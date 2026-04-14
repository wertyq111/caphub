<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInvocation;
use App\Models\TranslationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationJobController extends Controller
{
    /**
     * 分页查询翻译任务列表，参数：$request 分页与过滤参数。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $query = TranslationJob::query()
            ->with('result')
            ->orderByDesc('id');

        $mode = trim((string) $request->query('mode', ''));
        if (in_array($mode, ['sync', 'async'], true)) {
            $query->where('mode', $mode);
        }

        $paginator = $query->paginate($perPage);

        return response()->json($paginator);
    }

    /**
     * 查询单个翻译任务详情，参数：$job 路由绑定任务模型。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function show(TranslationJob $job): JsonResponse
    {
        $job->loadMissing('result');
        $latestInvocation = AiInvocation::query()
            ->where('job_id', $job->id)
            ->latest('created_at')
            ->latest('id')
            ->first();

        $resultMeta = (array) ($job->result?->meta_payload ?? []);

        return response()->json(array_merge($job->toArray(), [
            'translation_provider' => $this->resolveTranslationProvider($resultMeta, $latestInvocation),
            'translation_agent' => $this->resolveTranslationAgent($resultMeta, $latestInvocation),
        ]));
    }

    /**
     * 解析任务实际使用的翻译接口，优先使用结果元数据，缺失时回退到最近一次调用日志。
     * @since 2026-04-14
     * @param  array<string, mixed>  $resultMeta
     */
    protected function resolveTranslationProvider(array $resultMeta, ?AiInvocation $latestInvocation): ?string
    {
        $provider = data_get($resultMeta, 'provider');

        if (is_string($provider) && $provider !== '') {
            return $provider;
        }

        $provider = data_get($latestInvocation?->request_payload, 'execution_context.provider');

        if (is_string($provider) && $provider !== '') {
            return $provider;
        }

        $providerModel = (string) data_get($resultMeta, 'provider_model', '');

        if (str_starts_with($providerModel, 'openclaw/')) {
            return 'openclaw';
        }

        return null;
    }

    /**
     * 解析任务实际使用的 Agent 名称，优先使用调用日志，必要时从结果元数据中兜底。
     * @since 2026-04-14
     * @param  array<string, mixed>  $resultMeta
     */
    protected function resolveTranslationAgent(array $resultMeta, ?AiInvocation $latestInvocation): ?string
    {
        $agent = $latestInvocation?->agent_name;

        if (is_string($agent) && $agent !== '') {
            return $agent;
        }

        $agent = data_get($resultMeta, 'translation_agent');

        if (is_string($agent) && $agent !== '') {
            return $agent;
        }

        $providerModel = (string) data_get($resultMeta, 'provider_model', '');

        if (str_starts_with($providerModel, 'openclaw/')) {
            return substr($providerModel, strlen('openclaw/')) ?: null;
        }

        return null;
    }
}
