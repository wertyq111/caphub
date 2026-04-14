<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HermesChatController extends Controller
{
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

        $modelInfo = $this->fetchAvailableModels($apiKey, $timeout);

        $systemPrompt = implode("\n", [
            'You are CapHub Neural Link, an AI assistant for the CapHub chemical translation platform.',
            'You help users understand translation capabilities, agent status, and system operations.',
            'Respond concisely in the same language as the user message.',
            'If asked about translation, guide users to use the translation workbench at /demo/translate.',
            'You can discuss chemical industry terminology, translation quality, and system status.',
            '',
            '## Available Models/Agents on this platform:',
            $modelInfo,
            '',
            'When users ask about available models, respond with the actual model list above.',
        ]);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($request->input('history', []) as $turn) {
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

            $content = data_get($response->json(), 'choices.0.message.content', '');

            return response()->json([
                'reply' => trim((string) $content) ?: '抱歉，我暂时无法回复。',
                'error' => false,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'reply' => '连接 Hermes 服务时出现问题，请稍后重试。',
                'error' => true,
            ]);
        }
    }

    /**
     * Query all configured Hermes instances for their available models.
     */
    private function fetchAvailableModels(string $apiKey, int $timeout): string
    {
        $endpoints = [
            ['url' => config('services.hermes.chat_base_url', ''), 'role' => 'AI 对话助手 (Neural Link)'],
            ['url' => config('services.hermes.base_url', ''), 'role' => '化工翻译引擎'],
        ];

        $models = [];

        foreach ($endpoints as $ep) {
            $url = trim((string) $ep['url']);
            if ($url === '') continue;

            // Strip trailing /v1 or /v1/ if present to avoid double-path
            $url = preg_replace('#/v1/?$#', '', rtrim($url, '/'));

            try {
                $resp = Http::baseUrl($url)
                    ->acceptJson()
                    ->timeout(min($timeout, 10))
                    ->withToken($apiKey)
                    ->get('/v1/models');

                if ($resp->successful()) {
                    $data = $resp->json('data', []);
                    foreach ($data as $model) {
                        $id = $model['id'] ?? 'unknown';
                        $models[] = "- {$id} ({$ep['role']})";
                    }
                }
            } catch (\Throwable) {
                // Skip unreachable instances
            }
        }

        return $models
            ? implode("\n", $models)
            : '- 暂无法获取模型列表（Hermes 服务可能未就绪）';
    }
}
