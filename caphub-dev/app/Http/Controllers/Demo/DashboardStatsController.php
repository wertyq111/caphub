<?php

namespace App\Http\Controllers\Demo;

use App\Enums\TranslationProvider;
use App\Http\Controllers\Controller;
use App\Models\AiInvocation;
use App\Models\TranslationJob;
use App\Services\Translation\TranslationProviderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardStatsController extends Controller
{
    public function __invoke(TranslationProviderSettings $providerSettings): JsonResponse
    {
        $activeProvider = $providerSettings->current();

        $agents = array_map(function (TranslationProvider $provider) use ($providerSettings, $activeProvider): array {
            $providerKey = $provider->value;
            $configured = $providerSettings->isConfigured($provider);

            $recentStats = AiInvocation::query()
                ->where('request_payload->execution_context->provider', $providerKey)
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('COUNT(*) as total_calls')
                ->selectRaw('AVG(duration_ms) as avg_latency_ms')
                ->selectRaw("SUM(CASE WHEN status IN ('success', 'succeeded') THEN 1 ELSE 0 END) as succeeded")
                ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
                ->first();

            return [
                'key' => $providerKey,
                'name' => $provider === TranslationProvider::OpenClaw ? 'OpenClaw' : 'Hermes',
                'configured' => $configured,
                'active' => $provider === $activeProvider,
                'stats_24h' => [
                    'total_calls' => (int) ($recentStats->total_calls ?? 0),
                    'avg_latency_ms' => round((float) ($recentStats->avg_latency_ms ?? 0)),
                    'succeeded' => (int) ($recentStats->succeeded ?? 0),
                    'failed' => (int) ($recentStats->failed ?? 0),
                ],
            ];
        }, TranslationProvider::cases());

        $hourBucketExpression = $this->hourBucketExpression();

        $throughput = AiInvocation::query()
            ->where('created_at', '>=', now()->subHours(12))
            ->selectRaw("{$hourBucketExpression} as hour_bucket")
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->groupBy('hour_bucket')
            ->orderBy('hour_bucket')
            ->get()
            ->map(fn ($row) => [
                'hour' => $row->hour_bucket,
                'requests' => (int) $row->request_count,
                'avg_ms' => round((float) $row->avg_duration_ms),
            ]);

        $recentLogs = AiInvocation::query()
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'agent_name', 'status', 'duration_ms', 'created_at'])
            ->map(fn ($log) => [
                'id' => $log->id,
                'agent' => $log->agent_name,
                'status' => $this->normalizeStatus($log->status),
                'duration_ms' => $log->duration_ms,
                'time' => $log->created_at?->toIso8601String(),
            ]);

        $jobSummary = TranslationJob::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'succeeded' THEN 1 ELSE 0 END) as succeeded")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing")
            ->first();

        return response()->json([
            'agents' => $agents,
            'throughput' => $throughput,
            'recent_logs' => $recentLogs,
            'jobs_24h' => [
                'total' => (int) ($jobSummary->total ?? 0),
                'succeeded' => (int) ($jobSummary->succeeded ?? 0),
                'processing' => (int) ($jobSummary->processing ?? 0),
            ],
        ]);
    }

    protected function normalizeStatus(?string $status): ?string
    {
        if ($status === 'success') {
            return 'succeeded';
        }

        return $status;
    }

    protected function hourBucketExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d %H:00:00', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
        };
    }
}
