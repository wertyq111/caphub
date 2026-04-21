<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\Chat\CaphubProjectKnowledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HermesChatController extends Controller
{
    private const HISTORY_LIMIT = 6;

    private const HISTORY_CONTENT_LIMIT = 1200;

    public function __construct(
        protected CaphubProjectKnowledge $projectKnowledge,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:5000'],
        ]);

        $baseUrl = trim((string) config('services.hermes.chat_base_url', config('services.hermes.base_url', '')));
        $apiKey = trim((string) config('services.hermes.chat_api_key', config('services.hermes.api_key', '')));
        $profile = config('services.hermes.chat_profile', 'caphub-assistant');
        $timeout = max(1, (int) config('services.hermes.timeout', 120));

        if ($baseUrl === '' || $apiKey === '') {
            return response()->json([
                'reply' => '系统暂未配置 Hermes 服务，无法进行对话。',
                'error' => true,
            ]);
        }

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($apiKey, $timeout)],
        ];

        foreach ($this->sanitizeHistory($request->input('history', [])) as $turn) {
            $messages[] = [
                'role' => $turn['role'],
                'content' => $turn['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $request->input('message'),
        ];

        try {
            $response = Http::baseUrl(rtrim($baseUrl, '/'))
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->withToken($apiKey)
                ->post('/v1/chat/completions', [
                    'model' => $profile,
                    'stream' => false,
                    'messages' => $messages,
                ]);

            $response->throw();

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            if ($content === '') {
                return response()->json([
                    'reply' => '对话助手未返回内容，请稍后重试。',
                    'error' => true,
                ], 502);
            }

            return response()->json([
                'reply' => $content,
                'error' => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'reply' => '对话助手响应超时或上游无返回，请稍后重试。',
                'error' => true,
            ], 502);
        }
    }

    private function buildSystemPrompt(string $apiKey, int $timeout): string
    {
        return implode("\n", [
            'You are CapHub Neural Link, the resident assistant for the CapHub workspace.',
            'Reply in the same language as the user.',
            'Keep answers short, concrete, and grounded in the facts below.',
            'If the facts below do not cover the answer, say you are unsure.',
            'If asked to translate or operate the translation flow, direct the user to /demo/translate.',
            '',
            'Available agents on this platform:',
            $this->fetchAvailableModels($apiKey, $timeout),
            '',
            $this->projectKnowledge->build(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function sanitizeHistory(array $history): array
    {
        return collect($history)
            ->filter(fn (mixed $turn): bool => is_array($turn))
            ->filter(function (array $turn): bool {
                return in_array($turn['role'] ?? null, ['user', 'assistant'], true)
                    && is_string($turn['content'] ?? null);
            })
            ->slice(-self::HISTORY_LIMIT)
            ->map(fn (array $turn): array => [
                'role' => $turn['role'],
                'content' => $this->truncateContent($turn['content']),
            ])
            ->values()
            ->all();
    }

    private function truncateContent(string $content): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);

        if (mb_strlen($normalized) <= self::HISTORY_CONTENT_LIMIT) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, self::HISTORY_CONTENT_LIMIT)).'...';
    }

    /**
     * Query all configured Hermes instances for their available models.
     */
    private function fetchAvailableModels(string $apiKey, int $timeout): string
    {
        $endpoints = array_values(array_filter([
            ['url' => config('services.hermes.chat_base_url', ''), 'role' => 'AI 对话助手 (Neural Link)'],
            ['url' => config('services.hermes.base_url', ''), 'role' => '化工翻译引擎'],
        ], fn (array $endpoint): bool => trim((string) $endpoint['url']) !== ''));

        $cacheKey = 'hermes-chat-models:'.md5(json_encode($endpoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($apiKey, $endpoints, $timeout): string {
            $models = [];

            foreach ($endpoints as $ep) {
                $url = preg_replace('#/v1/?$#', '', rtrim((string) $ep['url'], '/'));

                try {
                    $resp = Http::baseUrl($url)
                        ->acceptJson()
                        ->timeout(min($timeout, 10))
                        ->withToken($apiKey)
                        ->get('/v1/models');

                    if ($resp->successful()) {
                        foreach ($resp->json('data', []) as $model) {
                            $id = $model['id'] ?? 'unknown';
                            $models[] = "- {$id} ({$ep['role']})";
                        }
                    }
                } catch (\Throwable) {
                    // Skip unreachable instances.
                }
            }

            return $models
                ? implode("\n", array_values(array_unique($models)))
                : '- 暂无法获取模型列表（Hermes 服务可能未就绪）';
        });
    }
}
