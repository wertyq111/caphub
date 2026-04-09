# Caphub 化工翻译平台 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 交付一个基于 Laravel + OpenClaw + Redis + MySQL 的化工资讯翻译后端，并新增以 Docker 方式运行的 `caphub-ui` Vue 3 前端，用于公开 demo 展示与后台管理。

**Architecture:** 先以 `caphub-dev` 建立翻译业务闭环，包括任务模型、术语库、OpenClaw 调用、同步/异步接口、缓存、限流与管理接口；再在同级目录新增 `caphub-ui`，使用 Tailwind CSS 构建 demo 前台、使用 Element Plus 构建 admin 后台，并从一开始就以 Docker 容器作为默认运行形态。平台化抽象只保留最小公共层：TaskCenter、AiCore、结果 schema、共享前端任务流。

**Tech Stack:** Laravel 13, PHP 8.3, MySQL, Redis, Laravel Queue, Laravel HTTP Client, Pest, Vue 3, Vite, Vue Router, Pinia, VueUse, @tanstack/vue-query, Tailwind CSS, Element Plus, Vitest, Docker, Docker Compose

---

## Preconditions

- 当前工作区快照没有 `.git` 元数据，因此下面所有 `git add` / `git commit` 步骤都应在真实 git clone 或工作树中执行。
- 当前项目根目录是 `caphub-dev`，新前端项目建议创建在同级目录 `../caphub-ui`，即 `/Volumes/AgentAPFS/Program/Agent/caphub/caphub-ui`。
- 本地与远端运行前，先清理所有 `._*` 文件，避免后续同步到远端 `/data/agent/projects/caphub` 时污染目录。
- 后端运行目录：`/Volumes/AgentAPFS/Program/Agent/caphub/caphub-dev`
- 远端后端运行目录：`/data/agent/projects/caphub/caphub-dev`
- 远端新前端目录建议：`/data/agent/projects/caphub/caphub-ui`
- `caphub-ui` 必须提供自己的 `Dockerfile` 与容器运行配置，不能把“宿主机直接执行 npm run dev/build”作为唯一交付路径。
- 后端测试统一使用 `Pest`。
- 每个对外接口必须补一份完整的 Feature 测试后，才能视为接口层完成。
- 单元测试可以补，但不作为继续推进的阻塞条件。
- 后端代码在本地编写，但后端调试、测试与运行验证统一放在远端 Docker 环境进行。
- 远端后端验证默认通过 Laravel Sail 执行，不把本地 `php artisan test` 或本地服务启动结果作为最终依据。
- 任何后端任务完成时，最终验证记录应优先给出远端 `sail` 命令与结果；本地验证只作为开发期快速自检。

## File Structure Map

### Backend: `caphub-dev`

- `bootstrap/app.php`
  负责注册 API 路由与中间件入口。
- `routes/api.php`
  负责 demo 与 admin API 路由。
- `config/services.php`
  负责 OpenClaw 配置。
- `config/cache.php`, `config/queue.php`
  负责缓存与队列驱动读取。
- `app/Http/Controllers/Demo/*`
  负责公开 demo 翻译接口。
- `app/Http/Controllers/Admin/*`
  负责后台登录、术语管理、任务查看、调用查看接口。
- `app/Http/Requests/Demo/*`, `app/Http/Requests/Admin/*`
  负责请求校验。
- `app/Models/*`
  负责 Eloquent 模型。
- `app/Domain/Translation/*`
  负责输入归一化、模式决策、翻译编排、结果组装。
- `app/Domain/Glossary/*`
  负责术语库匹配与预筛选。
- `app/Domain/TaskCenter/*`
  负责任务状态机与查询。
- `app/Infrastructure/Ai/OpenClaw/*`
  负责 OpenClaw 请求构造、调用与日志。
- `app/Jobs/*`
  负责异步任务执行与收尾。
- `database/migrations/*`
  负责任务、术语、命中、调用日志表结构。
- `tests/Feature/Demo/*`, `tests/Feature/Admin/*`, `tests/Unit/*`
  负责 Pest Feature 测试与按需补充的单元/集成测试，其中接口以 Feature 测试为准。

### Frontend: `caphub-ui`

- `../caphub-ui/package.json`
  负责前端依赖与脚本。
- `../caphub-ui/Dockerfile`
  负责前端容器镜像构建。
- `../caphub-ui/compose.yaml`
  负责前端本地/远端容器运行配置。
- `../caphub-ui/.dockerignore`
  负责前端容器构建上下文裁剪。
- `../caphub-ui/src/main.js`
  负责挂载 Vue、Router、Pinia、QueryClient、Element Plus。
- `../caphub-ui/src/router/index.js`
  负责 demo/admin 路由与守卫。
- `../caphub-ui/src/layouts/DemoLayout.vue`
  负责公开 demo 视觉外壳。
- `../caphub-ui/src/layouts/AdminLayout.vue`
  负责后台管理壳子。
- `../caphub-ui/src/api/*`
  负责 HTTP 客户端、翻译接口、后台接口。
- `../caphub-ui/src/composables/*`
  负责任务轮询、表单状态、认证状态。
- `../caphub-ui/src/pages/demo/*`
  负责 demo 页面。
- `../caphub-ui/src/pages/admin/*`
  负责后台页面。
- `../caphub-ui/src/components/demo/*`
  负责翻译输入、结果卡片、术语命中、风险提示、任务时间线。
- `../caphub-ui/src/components/admin/*`
  负责后台表格、表单、筛选器、统计卡片。
- `../caphub-ui/src/components/shared/*`
  负责共享的 loading、empty、error、badge 等基础组件。
- `../caphub-ui/src/**/*.spec.js`
  负责 Vitest 组件与 composable 测试。

## Task 1: Workspace Hygiene and API Entry Points

**Files:**
- Create: `routes/api.php`
- Create: `tests/Feature/Infrastructure/ApiPingTest.php`
- Modify: `bootstrap/app.php`
- Modify: `.gitignore`

- [ ] **Step 1: 写一个失败的 API 路由测试**

```php
<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class ApiPingTest extends TestCase
{
    public function test_api_ping_endpoint_returns_ok_json(): void
    {
        $response = $this->getJson('/api/ping');

        $response
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
```

- [ ] **Step 2: 运行测试确认当前失败**

Run: `php artisan test tests/Feature/Infrastructure/ApiPingTest.php`
Expected: FAIL with `404` because `routes/api.php` is not registered yet

- [ ] **Step 3: 注册 API 路由入口并添加最小 `/api/ping` 路由**

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['ok' => true]);
```

- [ ] **Step 4: 把 `._*` 文件加入忽略规则**

```gitignore
._*
.superpowers/
```

- [ ] **Step 5: 重新运行 API 路由测试**

Run: `php artisan test tests/Feature/Infrastructure/ApiPingTest.php`
Expected: PASS

- [ ] **Step 6: 在真实 git clone 中提交本任务**

```bash
git add bootstrap/app.php routes/api.php .gitignore tests/Feature/Infrastructure/ApiPingTest.php
git commit -m "chore: add api routing entrypoint"
```

## Task 2: Translation Job Domain and Pending Async Endpoint Skeleton

**Files:**
- Create: `app/Enums/TranslationJobStatus.php`
- Create: `app/Models/TranslationJob.php`
- Create: `app/Models/TranslationResult.php`
- Create: `app/Domain/TaskCenter/TaskStatusMachine.php`
- Create: `app/Domain/TaskCenter/TranslationJobService.php`
- Create: `database/migrations/2026_04_02_000003_create_translation_jobs_table.php`
- Create: `database/migrations/2026_04_02_000004_create_translation_results_table.php`
- Create: `tests/Feature/Demo/CreateTranslationAsyncJobTest.php`

- [ ] **Step 1: 写异步任务创建的 Pest Feature 测试**

```php
it('creates a pending translation job for async requests', function () {
    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => ['text' => '乙烯价格上涨。'],
    ]);

    $response->assertAccepted();

    $this->assertDatabaseHas('translation_jobs', [
        'input_type' => 'plain_text',
        'status' => 'pending',
    ]);
});
```

- [ ] **Step 2: 运行 Feature 测试确认失败**

Run: `php artisan test tests/Feature/Demo/CreateTranslationAsyncJobTest.php`
Expected: FAIL because the models, migration, and endpoint do not exist yet

- [ ] **Step 3: 添加任务状态枚举、任务模型与两张基础表**

```php
enum TranslationJobStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
```

```php
Schema::create('translation_jobs', function (Blueprint $table) {
    $table->id();
    $table->uuid('job_uuid')->unique();
    $table->string('mode');
    $table->string('status');
    $table->string('input_type');
    $table->string('document_type')->nullable();
    $table->string('source_lang');
    $table->string('target_lang');
    $table->longText('source_text')->nullable();
    $table->text('source_title')->nullable();
    $table->text('source_summary')->nullable();
    $table->longText('source_body')->nullable();
    $table->timestamps();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('finished_at')->nullable();
});
```

- [ ] **Step 4: 实现最小状态机与任务创建服务**

```php
public function createPendingAsyncJob(array $normalized): TranslationJob
{
    return TranslationJob::query()->create([
        'job_uuid' => (string) Str::uuid(),
        'mode' => 'async',
        'status' => TranslationJobStatus::Pending->value,
        'input_type' => $normalized['input_type'],
        'document_type' => $normalized['document_type'],
        'source_lang' => $normalized['source_lang'],
        'target_lang' => $normalized['target_lang'],
        'source_text' => $normalized['source_text'],
        'source_title' => $normalized['source_title'],
        'source_summary' => $normalized['source_summary'],
        'source_body' => $normalized['source_body'],
    ]);
}
```

- [ ] **Step 5: 重新运行 Feature 测试**

Run: `php artisan test tests/Feature/Demo/CreateTranslationAsyncJobTest.php`
Expected: PASS

- [ ] **Step 6: 如果状态机逻辑后续变复杂，再按需补单元测试**

Run: `php artisan test`
Expected: optional at this stage; not a blocking condition for moving to Task 3

- [ ] **Step 7: 在真实 git clone 中提交本任务**

```bash
git add app/Enums/TranslationJobStatus.php app/Models/TranslationJob.php app/Models/TranslationResult.php app/Domain/TaskCenter database/migrations tests/Feature/Demo/CreateTranslationAsyncJobTest.php
git commit -m "feat: add translation job domain"
```

## Task 3: Glossary Schema, Models, and Matcher

**Files:**
- Create: `app/Models/Glossary.php`
- Create: `app/Models/GlossaryAlias.php`
- Create: `app/Models/GlossaryForbiddenTranslation.php`
- Create: `app/Models/TranslationGlossaryHit.php`
- Create: `app/Domain/Glossary/GlossaryMatcher.php`
- Create: `app/Domain/Glossary/GlossaryPreselector.php`
- Create: `database/migrations/2026_04_02_000005_create_glossaries_table.php`
- Create: `database/migrations/2026_04_02_000006_create_glossary_aliases_table.php`
- Create: `database/migrations/2026_04_02_000007_create_glossary_forbidden_translations_table.php`
- Create: `database/migrations/2026_04_02_000008_create_translation_glossary_hits_table.php`
- [ ] **Step 1: 创建术语表、别名表、禁用译法表和命中记录表**

```php
Schema::create('glossaries', function (Blueprint $table) {
    $table->id();
    $table->string('term');
    $table->string('source_lang');
    $table->string('target_lang');
    $table->string('standard_translation');
    $table->string('domain')->default('chemical_news');
    $table->unsignedInteger('priority')->default(100);
    $table->string('status')->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 2: 创建术语模型与匹配服务**

```php
public function match(string $text, string $sourceLang, string $targetLang): array
{
    return Glossary::query()
        ->with(['aliases', 'forbiddenTranslations'])
        ->where('source_lang', $sourceLang)
        ->where('target_lang', $targetLang)
        ->get()
        ->flatMap(fn (Glossary $entry) => $this->matchEntry($entry, $text))
        ->all();
}
```

- [ ] **Step 3: 在后续接口 Feature 测试里覆盖术语命中行为**

Run: `php artisan test tests/Feature`
Expected: glossary behavior starts being exercised by endpoint-level Feature tests as translation APIs arrive

- [ ] **Step 4: 在真实 git clone 中提交本任务**

```bash
git add app/Models/Glossary*.php app/Models/TranslationGlossaryHit.php app/Domain/Glossary database/migrations
git commit -m "feat: add glossary schema and matcher"
```

## Task 4: OpenClaw Configuration and Client Layer

**Files:**
- Create: `app/Infrastructure/Ai/OpenClaw/OpenClawClient.php`
- Create: `app/Infrastructure/Ai/OpenClaw/TranslationAgentPayloadBuilder.php`
- Create: `app/Infrastructure/Ai/OpenClaw/OpenClawTranslationGateway.php`
- Create: `app/Infrastructure/Ai/OpenClaw/AiInvocationLogger.php`
- Create: `app/Models/AiInvocation.php`
- Create: `database/migrations/2026_04_02_000009_create_ai_invocations_table.php`
- Create: `tests/Feature/Infrastructure/OpenClawClientTest.php`
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] **Step 1: 写 OpenClaw 客户端集成测试，使用 HTTP fake**

```php
public function test_openclaw_client_posts_translation_payload(): void
{
    Http::fake([
        '*' => Http::response([
            'translated_document' => ['text' => 'Ethylene prices rose.'],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => ['schema_version' => 'v1'],
        ], 200),
    ]);

    $response = app(OpenClawClient::class)->translate([
        'task_type' => 'translation',
        'task_subtype' => 'chemical_news',
    ]);

    Http::assertSentCount(1);
    $this->assertSame('v1', $response['meta']['schema_version']);
}
```

- [ ] **Step 2: 运行 OpenClaw 客户端测试确认失败**

Run: `php artisan test tests/Feature/Infrastructure/OpenClawClientTest.php`
Expected: FAIL because the client and configuration do not exist yet

- [ ] **Step 3: 在 `config/services.php` 和 `.env.example` 中增加 OpenClaw 配置**

```php
'openclaw' => [
    'base_url' => env('OPENCLAW_BASE_URL'),
    'api_key' => env('OPENCLAW_API_KEY'),
    'translation_agent' => env('OPENCLAW_TRANSLATION_AGENT', 'chemical-news-translator'),
    'timeout' => (int) env('OPENCLAW_TIMEOUT', 30),
],
```

```dotenv
OPENCLAW_BASE_URL=
OPENCLAW_API_KEY=
OPENCLAW_TRANSLATION_AGENT=chemical-news-translator
OPENCLAW_TIMEOUT=30
```

- [ ] **Step 4: 实现请求构造器、客户端和调用日志**

```php
public function build(array $document, array $glossaryEntries): array
{
    return [
        'task_type' => 'translation',
        'task_subtype' => 'chemical_news',
        'input_document' => $document,
        'context' => [
            'source_lang' => $document['source_lang'],
            'target_lang' => $document['target_lang'],
            'glossary_entries' => $glossaryEntries,
            'constraints' => [
                'preserve_units' => true,
                'preserve_entities' => true,
            ],
        ],
        'output_schema_version' => 'v1',
    ];
}
```

- [ ] **Step 5: 重新运行 OpenClaw 客户端测试**

Run: `php artisan test tests/Feature/Infrastructure/OpenClawClientTest.php`
Expected: PASS

- [ ] **Step 6: 在真实 git clone 中提交本任务**

```bash
git add app/Infrastructure/Ai/OpenClaw app/Models/AiInvocation.php database/migrations config/services.php .env.example tests/Feature/Infrastructure/OpenClawClientTest.php
git commit -m "feat: add openclaw client integration"
```

## Task 5: Sync Translation API

**Files:**
- Create: `app/Http/Requests/Demo/StoreSyncTranslationRequest.php`
- Create: `app/Http/Controllers/Demo/SyncTranslationController.php`
- Create: `app/Domain/Translation/TranslationRequestNormalizer.php`
- Create: `app/Domain/Translation/TranslationModeResolver.php`
- Create: `app/Domain/Translation/TranslationService.php`
- Create: `app/Domain/Translation/TranslationResponseFactory.php`
- Create: `tests/Feature/Demo/SyncTranslateTextTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: 写同步翻译接口功能测试**

```php
public function test_sync_translation_returns_translated_document_shape(): void
{
    Http::fake([
        '*' => Http::response([
            'translated_document' => ['text' => 'Ethylene prices rose.'],
            'glossary_hits' => [],
            'risk_flags' => [],
            'notes' => [],
            'meta' => ['schema_version' => 'v1'],
        ], 200),
    ]);

    $response = $this->postJson('/api/demo/translate/sync', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => ['text' => '乙烯价格上涨。'],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('translated_document.text', 'Ethylene prices rose.')
        ->assertJsonPath('meta.schema_version', 'v1');
}
```

- [ ] **Step 2: 运行同步接口测试确认失败**

Run: `php artisan test tests/Feature/Demo/SyncTranslateTextTest.php`
Expected: FAIL because the request class, route, controller, and translation service do not exist yet

- [ ] **Step 3: 实现输入归一化和同步翻译控制器**

```php
public function __invoke(StoreSyncTranslationRequest $request): JsonResponse
{
    $normalized = $this->normalizer->normalize($request->validated());
    $result = $this->translationService->translateSync($normalized);

    return response()->json($this->responseFactory->fromSyncResult($result));
}
```

- [ ] **Step 4: 在 `routes/api.php` 注册同步翻译路由**

```php
Route::prefix('demo/translate')->group(function () {
    Route::post('/sync', SyncTranslationController::class);
});
```

- [ ] **Step 5: 重新运行同步接口测试**

Run: `php artisan test tests/Feature/Demo/SyncTranslateTextTest.php`
Expected: PASS

- [ ] **Step 6: 在真实 git clone 中提交本任务**

```bash
git add app/Http/Requests/Demo/StoreSyncTranslationRequest.php app/Http/Controllers/Demo/SyncTranslationController.php app/Domain/Translation routes/api.php tests/Feature/Demo/SyncTranslateTextTest.php
git commit -m "feat: add sync translation api"
```

## Task 6: Async Translation API, Queue Worker, and Job Polling

**Files:**
- Create: `app/Http/Requests/Demo/StoreAsyncTranslationRequest.php`
- Create: `app/Http/Controllers/Demo/AsyncTranslationController.php`
- Create: `app/Http/Controllers/Demo/ShowTranslationJobController.php`
- Create: `app/Http/Controllers/Demo/ShowTranslationResultController.php`
- Create: `app/Jobs/ProcessTranslationJob.php`
- Create: `app/Jobs/FinalizeTranslationJob.php`
- Create: `tests/Feature/Demo/AsyncTranslateJobFlowTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: 写异步任务流功能测试**

```php
public function test_async_translation_dispatches_job_and_allows_polling(): void
{
    Bus::fake();

    $response = $this->postJson('/api/demo/translate/async', [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => ['text' => '乙烯价格上涨。'],
    ]);

    $response->assertAccepted()->assertJsonStructure(['job_id', 'status']);

    Bus::assertDispatched(ProcessTranslationJob::class);
}
```

- [ ] **Step 2: 运行异步任务流测试确认失败**

Run: `php artisan test tests/Feature/Demo/AsyncTranslateJobFlowTest.php`
Expected: FAIL because async controllers and queue jobs do not exist yet

- [ ] **Step 3: 创建异步控制器与任务查询接口**

```php
Route::prefix('demo/translate')->group(function () {
    Route::post('/async', AsyncTranslationController::class);
    Route::get('/jobs/{job}', ShowTranslationJobController::class);
    Route::get('/jobs/{job}/result', ShowTranslationResultController::class);
});
```

- [ ] **Step 4: 实现 `ProcessTranslationJob` 和 `FinalizeTranslationJob`**

```php
public function handle(TranslationService $translationService): void
{
    $this->jobService->markProcessing($this->jobId);
    $result = $translationService->translateAsyncJob($this->jobId);

    FinalizeTranslationJob::dispatch($this->jobId, $result);
}
```

- [ ] **Step 5: 重新运行异步任务流测试**

Run: `php artisan test tests/Feature/Demo/AsyncTranslateJobFlowTest.php`
Expected: PASS

- [ ] **Step 6: 在真实 git clone 中提交本任务**

```bash
git add app/Http/Requests/Demo/StoreAsyncTranslationRequest.php app/Http/Controllers/Demo app/Jobs routes/api.php tests/Feature/Demo/AsyncTranslateJobFlowTest.php
git commit -m "feat: add async translation job flow"
```

## Task 7: Result Persistence, Glossary Hits, Cache, and Rate Limiting

**Files:**
- Create: `app/Domain/Translation/TranslationResultPersister.php`
- Create: `app/Domain/Translation/GlossaryHitPersister.php`
- Create: `app/Models/DemoAccessLog.php`
- Create: `database/migrations/2026_04_02_000010_create_demo_access_logs_table.php`
- Create: `tests/Feature/Demo/SyncTranslationCacheTest.php`
- Create: `tests/Feature/Demo/DemoRateLimitTest.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Domain/Translation/TranslationService.php`

- [ ] **Step 1: 写缓存命中测试**

```php
public function test_identical_sync_translation_uses_cache_on_second_request(): void
{
    Cache::flush();
    Http::fake(['*' => Http::response([
        'translated_document' => ['text' => 'Ethylene prices rose.'],
        'glossary_hits' => [],
        'risk_flags' => [],
        'notes' => [],
        'meta' => ['schema_version' => 'v1'],
    ], 200)]);

    $payload = [
        'input_type' => 'plain_text',
        'source_lang' => 'zh',
        'target_lang' => 'en',
        'content' => ['text' => '乙烯价格上涨。'],
    ];

    $this->postJson('/api/demo/translate/sync', $payload)->assertOk();
    $this->postJson('/api/demo/translate/sync', $payload)
        ->assertOk()
        ->assertJsonPath('meta.cache_hit', true);
}
```

- [ ] **Step 2: 写 demo 限流测试**

```php
public function test_demo_sync_endpoint_is_rate_limited(): void
{
    for ($i = 0; $i < 11; $i++) {
        $response = $this->postJson('/api/demo/translate/sync', [
            'input_type' => 'plain_text',
            'source_lang' => 'zh',
            'target_lang' => 'en',
            'content' => ['text' => '乙烯价格上涨。'],
        ]);
    }

    $response->assertStatus(429);
}
```

- [ ] **Step 3: 运行缓存与限流测试确认失败**

Run: `php artisan test tests/Feature/Demo/SyncTranslationCacheTest.php tests/Feature/Demo/DemoRateLimitTest.php`
Expected: FAIL because cache metadata and rate limiting are not implemented yet

- [ ] **Step 4: 在 `AppServiceProvider` 中注册 demo 限流器**

```php
RateLimiter::for('demo-translation', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});
```

- [ ] **Step 5: 在翻译服务中加入缓存与结果持久化**

```php
$cacheKey = sprintf(
    'translation:%s:%s:%s:%s',
    sha1($normalized['raw_cache_payload']),
    $normalized['source_lang'],
    $normalized['target_lang'],
    $normalized['glossary_version']
);
```

- [ ] **Step 6: 重新运行缓存与限流测试**

Run: `php artisan test tests/Feature/Demo/SyncTranslationCacheTest.php tests/Feature/Demo/DemoRateLimitTest.php`
Expected: PASS

- [ ] **Step 7: 在真实 git clone 中提交本任务**

```bash
git add app/Domain/Translation app/Providers/AppServiceProvider.php app/Models/DemoAccessLog.php database/migrations tests/Feature/Demo/SyncTranslationCacheTest.php tests/Feature/Demo/DemoRateLimitTest.php
git commit -m "feat: add translation persistence cache and rate limiting"
```

## Task 8: Admin Authentication and Admin APIs

**Files:**
- Create: `app/Http/Controllers/Admin/Auth/LoginController.php`
- Create: `app/Http/Controllers/Admin/GlossaryController.php`
- Create: `app/Http/Controllers/Admin/TranslationJobController.php`
- Create: `app/Http/Controllers/Admin/AiInvocationController.php`
- Create: `app/Http/Requests/Admin/LoginRequest.php`
- Create: `app/Http/Requests/Admin/StoreGlossaryRequest.php`
- Create: `app/Http/Requests/Admin/UpdateGlossaryRequest.php`
- Create: `tests/Feature/Admin/LoginTest.php`
- Create: `tests/Feature/Admin/GlossaryCrudTest.php`
- Modify: `routes/api.php`
- Modify: `app/Models/User.php`
- Modify: `composer.json`
- Create after package install: `config/sanctum.php`

- [x] **Step 1: 添加 Sanctum 依赖**

Run: `composer require laravel/sanctum`
Expected: `composer.json`, `composer.lock`, `config/sanctum.php`, and Sanctum migration become available

- [x] **Step 2: 写后台登录测试**

```php
public function test_admin_can_log_in_and_receive_token(): void
{
    $user = User::factory()->create(['email' => 'admin@example.com']);

    $response = $this->postJson('/api/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonStructure(['token', 'user']);
}
```

- [x] **Step 3: 写术语 CRUD 测试**

```php
public function test_authenticated_admin_can_create_glossary_entry(): void
{
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/admin/glossaries', [
        'term' => 'ethylene',
        'source_lang' => 'en',
        'target_lang' => 'zh',
        'standard_translation' => '乙烯',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('glossaries', ['term' => 'ethylene']);
}
```

- [x] **Step 4: 运行后台登录与 CRUD 测试确认失败**

Run: `php artisan test tests/Feature/Admin/LoginTest.php tests/Feature/Admin/GlossaryCrudTest.php`
Expected: FAIL because Sanctum, admin routes, and controllers are not in place yet

- [x] **Step 5: 实现后台登录与受保护的管理路由**

```php
Route::prefix('admin')->group(function () {
    Route::post('/login', LoginController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('glossaries', GlossaryController::class)->only(['index', 'store', 'update']);
        Route::get('/translation-jobs', [TranslationJobController::class, 'index']);
        Route::get('/translation-jobs/{job}', [TranslationJobController::class, 'show']);
        Route::get('/ai-invocations', [AiInvocationController::class, 'index']);
    });
});
```

- [x] **Step 6: 重新运行后台登录与 CRUD 测试**

Run: `php artisan test tests/Feature/Admin/LoginTest.php tests/Feature/Admin/GlossaryCrudTest.php`
Expected: PASS

- [ ] **Step 7: 在真实 git clone 中提交本任务**

```bash
git add composer.json composer.lock config/sanctum.php app/Http/Controllers/Admin app/Http/Requests/Admin app/Models/User.php routes/api.php tests/Feature/Admin
git commit -m "feat: add admin auth and glossary apis"
```

## Task 9: Bootstrap `caphub-ui` Project

**Files:**
- Create: `../caphub-ui/package.json`
- Create: `../caphub-ui/Dockerfile`
- Create: `../caphub-ui/compose.yaml`
- Create: `../caphub-ui/.dockerignore`
- Create: `../caphub-ui/vite.config.js`
- Create: `../caphub-ui/index.html`
- Create: `../caphub-ui/src/main.js`
- Create: `../caphub-ui/src/App.vue`
- Create: `../caphub-ui/src/router/index.js`
- Create: `../caphub-ui/src/layouts/DemoLayout.vue`
- Create: `../caphub-ui/src/layouts/AdminLayout.vue`
- Create: `../caphub-ui/src/api/http.js`
- Create: `../caphub-ui/src/stores/auth.js`
- Create: `../caphub-ui/src/composables/useJobPolling.js`
- Create: `../caphub-ui/src/styles/tailwind.css`
- Create: `../caphub-ui/src/components/shared/AppLoader.vue`
- Create: `../caphub-ui/src/components/shared/AppErrorState.vue`
- Create: `../caphub-ui/src/router/__tests__/router-layouts.spec.js`

- [x] **Step 1: 初始化 Vue 3 + Vite 工程目录**

Run: `mkdir -p /Volumes/AgentAPFS/Program/Agent/caphub/caphub-ui`
Expected: sibling project directory exists next to `caphub-dev`

- [x] **Step 2: 写一个失败的路由布局测试**

```js
import { describe, expect, it } from 'vitest';
import { routes } from '../index';

describe('router layouts', () => {
  it('uses DemoLayout for demo pages and AdminLayout for admin pages', () => {
    const demo = routes.find((route) => route.path === '/demo');
    const admin = routes.find((route) => route.path === '/admin');

    expect(demo.meta.layout).toBe('demo');
    expect(admin.meta.layout).toBe('admin');
  });
});
```

- [x] **Step 3: 安装前端基础依赖**

Run: `npm install vue vue-router pinia @vueuse/core @tanstack/vue-query axios tailwindcss @tailwindcss/vite element-plus @element-plus/icons-vue`
Expected: `package.json` and lockfile updated with runtime dependencies

- [x] **Step 4: 安装测试依赖**

Run: `npm install -D vite @vitejs/plugin-vue vitest jsdom @vue/test-utils`
Expected: Vitest available for component and router tests

- [x] **Step 5: 创建基础项目壳子与双布局路由**

```js
export const routes = [
  {
    path: '/demo',
    component: () => import('../layouts/DemoLayout.vue'),
    meta: { layout: 'demo' },
    children: [],
  },
  {
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { layout: 'admin' },
    children: [],
  },
];
```

- [x] **Step 6: 增加前端 Docker 运行文件**

```dockerfile
FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
```

```yaml
services:
  caphub-ui:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8088:5173"
    environment:
      VITE_API_BASE_URL: http://host.docker.internal:8090/api
    volumes:
      - .:/app
      - /app/node_modules
```

- [x] **Step 7: 运行前端路由测试**

Run: `npm run test -- src/router/__tests__/router-layouts.spec.js`
Expected: PASS

- [x] **Step 8: 运行前端 Docker 验证**

Run: `docker compose up -d --build`
Expected: frontend container starts and Vite dev server is reachable from the mapped port

- [ ] **Step 9: 在真实 git clone 中提交本任务**

```bash
git add ../caphub-ui
git commit -m "feat: scaffold caphub ui project"
```

## Task 10: Build Demo Pages and Translation Experience

**Files:**
- Create: `../caphub-ui/src/pages/demo/TranslatePage.vue`
- Create: `../caphub-ui/src/pages/demo/JobPage.vue`
- Create: `../caphub-ui/src/pages/demo/ResultPage.vue`
- Create: `../caphub-ui/src/components/demo/TranslationInputPanel.vue`
- Create: `../caphub-ui/src/components/demo/TranslatedDocumentCard.vue`
- Create: `../caphub-ui/src/components/demo/GlossaryHitsPanel.vue`
- Create: `../caphub-ui/src/components/demo/RiskFlagsPanel.vue`
- Create: `../caphub-ui/src/components/demo/JobTimeline.vue`
- Create: `../caphub-ui/src/api/translation.js`
- Create: `../caphub-ui/src/components/demo/__tests__/TranslationInputPanel.spec.js`
- Modify: `../caphub-ui/src/router/index.js`

- [x] **Step 1: 写 Demo 输入面板组件测试**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TranslationInputPanel from '../TranslationInputPanel.vue';

describe('TranslationInputPanel', () => {
  it('switches between plain_text and article_payload modes', async () => {
    const wrapper = mount(TranslationInputPanel);

    await wrapper.find('[data-mode="article_payload"]').trigger('click');

    expect(wrapper.emitted('mode-change')[0]).toEqual(['article_payload']);
  });
});
```

- [x] **Step 2: 运行组件测试确认失败**

Run: `npm run test -- src/components/demo/__tests__/TranslationInputPanel.spec.js`
Expected: FAIL because demo components do not exist yet

- [x] **Step 3: 创建 demo 路由与页面**

```js
{
  path: '/demo/translate',
  component: () => import('../pages/demo/TranslatePage.vue'),
},
{
  path: '/demo/jobs/:jobId',
  component: () => import('../pages/demo/JobPage.vue'),
},
{
  path: '/demo/results/:jobId',
  component: () => import('../pages/demo/ResultPage.vue'),
},
```

- [x] **Step 4: 实现翻译输入、任务轮询与结果展示组件**

```js
export function useJobPolling(jobIdRef) {
  return useQuery({
    queryKey: ['translation-job', jobIdRef],
    queryFn: () => fetchTranslationJob(jobIdRef.value),
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === 'succeeded' || status === 'failed' ? false : 2000;
    },
  });
}
```

- [x] **Step 5: 重新运行 Demo 输入面板测试**

Run: `npm run test -- src/components/demo/__tests__/TranslationInputPanel.spec.js`
Expected: PASS

- [ ] **Step 6: 本地运行前端并手动验证 Demo 页面**

Run: `docker compose up -d`
Expected: `/demo/translate` 可访问，表单支持文本模式与资讯模式切换

- [ ] **Step 7: 在真实 git clone 中提交本任务**

```bash
git add ../caphub-ui/src/pages/demo ../caphub-ui/src/components/demo ../caphub-ui/src/api/translation.js ../caphub-ui/src/router/index.js
git commit -m "feat: add demo translation pages"
```

## Task 11: Build Admin Pages and Operational Views

**Files:**
- Create: `../caphub-ui/src/pages/admin/LoginPage.vue`
- Create: `../caphub-ui/src/pages/admin/DashboardPage.vue`
- Create: `../caphub-ui/src/pages/admin/GlossaryPage.vue`
- Create: `../caphub-ui/src/pages/admin/JobsPage.vue`
- Create: `../caphub-ui/src/pages/admin/JobDetailPage.vue`
- Create: `../caphub-ui/src/pages/admin/InvocationsPage.vue`
- Create: `../caphub-ui/src/components/admin/GlossaryTable.vue`
- Create: `../caphub-ui/src/components/admin/GlossaryFormDialog.vue`
- Create: `../caphub-ui/src/components/admin/JobTable.vue`
- Create: `../caphub-ui/src/components/admin/InvocationTable.vue`
- Create: `../caphub-ui/src/api/admin.js`
- Create: `../caphub-ui/src/components/admin/__tests__/GlossaryTable.spec.js`
- Modify: `../caphub-ui/src/router/index.js`
- Modify: `../caphub-ui/src/stores/auth.js`

- [x] **Step 1: 写后台术语表格测试**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import GlossaryTable from '../GlossaryTable.vue';

describe('GlossaryTable', () => {
  it('renders glossary rows', () => {
    const wrapper = mount(GlossaryTable, {
      props: {
        rows: [{ id: 1, term: 'ethylene', standard_translation: '乙烯' }],
      },
    });

    expect(wrapper.text()).toContain('ethylene');
    expect(wrapper.text()).toContain('乙烯');
  });
});
```

- [x] **Step 2: 运行后台表格测试确认失败**

Run: `npm run test -- src/components/admin/__tests__/GlossaryTable.spec.js`
Expected: FAIL because admin pages and table components do not exist yet

- [x] **Step 3: 创建后台页面路由与认证守卫**

```js
{
  path: '/admin/login',
  component: () => import('../pages/admin/LoginPage.vue'),
},
{
  path: '/admin/glossaries',
  component: () => import('../pages/admin/GlossaryPage.vue'),
  meta: { requiresAuth: true },
},
```

- [x] **Step 4: 使用 Element Plus 实现后台核心页面**

```js
const app = createApp(App);
app.use(ElementPlus);
```

Use `el-table`, `el-form`, `el-dialog`, `el-pagination`, and `el-select` for:

- glossary CRUD
- job list and detail
- invocation inspection
- admin dashboard summaries

- [x] **Step 5: 重新运行后台表格测试**

Run: `npm run test -- src/components/admin/__tests__/GlossaryTable.spec.js`
Expected: PASS

- [ ] **Step 6: 本地运行前端并手动验证后台页面**

Run: `docker compose up -d`
Expected: `/admin/login` 可登录，`/admin/glossaries`、`/admin/jobs`、`/admin/invocations` 可正常导航与渲染

- [ ] **Step 7: 在真实 git clone 中提交本任务**

```bash
git add ../caphub-ui/src/pages/admin ../caphub-ui/src/components/admin ../caphub-ui/src/api/admin.js ../caphub-ui/src/router/index.js ../caphub-ui/src/stores/auth.js
git commit -m "feat: add admin ui pages"
```

## Final Verification Checklist

- [ ] 清理本地所有 `._*` 文件：`find . -name '._*' -delete`
- [ ] 后端测试通过：`php artisan test`
- [ ] 前端测试通过：`npm run test`
- [ ] 前端容器可启动：`docker compose up -d --build`
- [ ] 后端 Pint 检查通过：`./vendor/bin/pint --test`
- [ ] Demo 页面本地可访问并完成一次同步翻译
- [ ] Demo 页面本地可访问并完成一次异步翻译轮询
- [ ] 后台可登录并完成一次术语创建
- [ ] 后台可查看任务列表与调用日志
- [ ] 远端同步前再次确认未包含 `._*` 文件
- [ ] 远端运行验证前，先进入 `/data/agent/projects/caphub/caphub-dev`

## Execution Notes

- 先完成 Task 1 到 Task 8，使后端翻译能力闭环。
- 在后端闭环通过后，再执行 Task 9 到 Task 11，构建 `caphub-ui`。
- 如果需要更快交付首个可用版本，可以先只完成 `TranslatePage.vue`、`JobPage.vue`、`GlossaryPage.vue`，然后再补齐 Dashboard 与 Invocation 视图。
