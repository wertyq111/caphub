<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGlossaryRequest;
use App\Http\Requests\Admin\UpdateGlossaryRequest;
use App\Models\Glossary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlossaryController extends Controller
{
    /**
     * 分页查询术语表列表，参数：$request 分页参数。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $paginator = Glossary::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($paginator);
    }

    /**
     * 创建术语表记录，参数：$request 术语表创建请求。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function store(StoreGlossaryRequest $request): JsonResponse
    {
        $glossary = Glossary::query()->create($request->validated());

        return response()->json($glossary, 201);
    }

    /**
     * 更新术语表记录，参数：$request 更新请求，$glossary 路由绑定术语模型。
     * @since 2026-04-02
     * @author zhouxufeng
     */
    public function update(UpdateGlossaryRequest $request, Glossary $glossary): JsonResponse
    {
        $glossary->update($request->validated());

        return response()->json($glossary->fresh());
    }

    /**
     * 删除术语表记录，参数：$glossary 路由绑定术语模型。
     * @since 2026-04-08
     * @author zhouxufeng
     */
    public function destroy(Glossary $glossary)
    {
        $glossary->delete();

        return response()->noContent();
    }
}
