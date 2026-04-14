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

        $systemPrompt = implode("\n", [
            'You are CapHub Neural Link, an AI assistant for the CapHub chemical translation platform.',
            'You help users understand translation capabilities, agent status, and system operations.',
            'Respond concisely in the same language as the user message.',
            'If asked about translation, guide users to use the translation workbench.',
            'You can discuss chemical industry terminology, translation quality, and system status.',
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
}
