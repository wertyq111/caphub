<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('sends chat completions through the current caphub assistant profile with project knowledge attached', function () {
    Cache::flush();

    $workspaceRoot = fakeWorkspaceRoot(includeProjectInfo: false);

    config()->set('services.hermes', [
        'base_url' => 'https://translate.example.test/v1',
        'api_key' => 'translate-key',
        'chat_base_url' => 'https://chat.example.test',
        'chat_api_key' => 'chat-key',
        'chat_profile' => 'caphub-assistant',
        'workspace_root' => $workspaceRoot,
        'timeout' => 30,
    ]);

    Http::fake([
        'https://chat.example.test/v1/models' => Http::response([
            'data' => [
                ['id' => 'caphub-assistant'],
            ],
        ], 200),
        'https://translate.example.test/v1/models' => Http::response([
            'data' => [
                ['id' => 'chemical-news-translator'],
            ],
        ], 200),
        'https://chat.example.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => '这是站点专家回复。',
                ],
            ]],
        ], 200),
    ]);

    $this->postJson('/api/demo/chat', [
        'message' => '这个网站有哪些功能？',
        'history' => [
            ['role' => 'assistant', 'content' => '欢迎使用。'],
        ],
    ])
        ->assertOk()
        ->assertJson([
            'reply' => '这是站点专家回复。',
            'error' => false,
        ]);

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://chat.example.test/v1/chat/completions') {
            return false;
        }

        $payload = json_decode($request->body(), true);
        $systemPrompt = (string) data_get($payload, 'messages.0.content', '');

        expect($payload['model'])->toBe('caphub-assistant');
        expect($systemPrompt)->toContain('Use the embedded project knowledge as your source of truth before answering.');
        expect($systemPrompt)->toContain('CapHub Demo Workspace');
        expect($systemPrompt)->toContain('/demo/translate');
        expect($systemPrompt)->toContain('/admin/glossaries');
        expect($systemPrompt)->toContain('POST /api/demo/chat');
        expect($systemPrompt)->toContain('Vue 3');
        expect($systemPrompt)->toContain('chemical-news-translator');
        expect(data_get($payload, 'messages.1.content'))->toBe('欢迎使用。');
        expect(data_get($payload, 'messages.2.content'))->toBe('这个网站有哪些功能？');

        return true;
    });
});

it('fails fast when the chat project knowledge workspace is missing', function () {
    Cache::flush();

    config()->set('services.hermes', [
        'base_url' => 'https://translate.example.test/v1',
        'api_key' => 'translate-key',
        'chat_base_url' => 'https://chat.example.test',
        'chat_api_key' => 'chat-key',
        'chat_profile' => 'caphub-assistant',
        'workspace_root' => sys_get_temp_dir().'/missing-caphub-workspace',
        'timeout' => 30,
    ]);

    Http::fake([
        'https://chat.example.test/v1/models' => Http::response(['data' => []], 200),
        'https://translate.example.test/v1/models' => Http::response(['data' => []], 200),
    ]);

    $this->postJson('/api/demo/chat', [
        'message' => '你好',
    ])
        ->assertStatus(500);

    Http::assertNotSent(function (Request $request): bool {
        return $request->url() === 'https://chat.example.test/v1/chat/completions';
    });
});

function fakeWorkspaceRoot(bool $includeProjectInfo = true): string
{
    $root = sys_get_temp_dir().'/caphub-chat-workspace-'.bin2hex(random_bytes(5));

    mkdir($root, 0777, true);
    mkdir($root.'/caphub-dev/routes', 0777, true);
    mkdir($root.'/caphub-ui/src/router', 0777, true);

    if ($includeProjectInfo) {
        file_put_contents($root.'/project-info.json', json_encode([
            'name' => 'CapHub Demo Workspace',
            'stack' => [
                'backend' => ['framework' => 'Laravel 13'],
                'frontend' => ['framework' => 'Vue 3'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    file_put_contents($root.'/README.md', <<<'MD'
# CapHub Demo Workspace

CapHub 是面向化工资讯翻译和术语治理的网站。
Demo 页面包含 `/demo/translate`、`/demo/jobs/:jobId`、`/demo/results/:jobId`。
Admin 页面包含 `/admin/dashboard`、`/admin/glossaries`、`/admin/jobs`、`/admin/invocations`。
MD);

    file_put_contents($root.'/caphub-dev/README.md', <<<'MD'
# Backend

- POST /api/demo/chat
- POST /api/demo/translate/sync
- POST /api/demo/translate/async
MD);

    file_put_contents($root.'/caphub-dev/routes/api.php', <<<'PHP'
<?php

Route::post('/demo/chat', HermesChatController::class);
Route::post('/demo/translate/sync', SyncTranslationController::class);
PHP);

    file_put_contents($root.'/caphub-ui/README.md', <<<'MD'
# Frontend

- Vue 3
- `/demo/translate`
- `/admin/glossaries`
MD);

    file_put_contents($root.'/caphub-ui/src/router/index.js', <<<'JS'
export const routes = [
  { path: '/demo/translate', name: 'demo-translate' },
  { path: '/admin/glossaries', name: 'admin-glossaries' },
];
JS);

    return $root;
}
