<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return response()->json($job);
    }
}
