<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInvocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInvocationController extends Controller
{
    /**
     * 分页查询 AI 调用日志，参数：$request 分页参数。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $paginator = AiInvocation::query()
            ->with('translationJob')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($paginator);
    }
}
