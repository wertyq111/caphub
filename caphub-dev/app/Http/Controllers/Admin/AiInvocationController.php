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
            ->paginate($perPage)
            ->through(function (AiInvocation $invocation): array {
                $payload = $invocation->toArray();
                $payload['status'] = $this->normalizeStatus($invocation->status);
                $payload['text_bytes'] = $this->resolveTextBytes($invocation);

                return $payload;
            });

        return response()->json($paginator);
    }

    protected function normalizeStatus(?string $status): ?string
    {
        if ($status === 'success') {
            return 'succeeded';
        }

        return $status;
    }

    protected function resolveTextBytes(AiInvocation $invocation): ?int
    {
        $jobBytes = $this->sumStringBytes([
            $invocation->translationJob?->source_title,
            $invocation->translationJob?->source_summary,
            $invocation->translationJob?->source_body,
            $invocation->translationJob?->source_text,
        ]);

        if ($jobBytes !== null) {
            return $jobBytes;
        }

        $documentByteLengths = data_get($invocation->request_payload, 'document_byte_lengths');

        if (! is_array($documentByteLengths)) {
            return null;
        }

        $total = collect($documentByteLengths)
            ->filter(static fn (mixed $value): bool => is_int($value) || is_float($value))
            ->sum(static fn (mixed $value): int => max(0, (int) round($value)));

        return $total > 0 ? $total : null;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function sumStringBytes(array $values): ?int
    {
        $strings = collect($values)
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '');

        if ($strings->isEmpty()) {
            return null;
        }

        return $strings->sum(static fn (string $value): int => strlen($value));
    }
}
