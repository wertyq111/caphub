# CapHub Backend

`caphub-dev` 是 CapHub 的 Laravel 后端，负责提供 Demo 翻译接口、异步任务编排、术语治理、后台管理 API 和 AI 调用审计。

## 技术栈

- PHP `^8.3`
- Laravel `^13`
- Laravel Sanctum
- Laravel Queue
- MySQL 8.4
- Redis
- SQLite
- Pest / PHPUnit

## 主要能力

### Demo API

- `GET /api/ping`
- `GET /api/demo/dashboard/stats`
- `POST /api/demo/chat`
- `POST /api/demo/translate/sync`
- `POST /api/demo/translate/async`
- `GET /api/demo/translate/jobs/{jobUuid}`
- `GET /api/demo/translate/jobs/{jobUuid}/result`

### Admin API

- `POST /api/admin/login`
- `GET /api/admin/glossaries`
- `POST /api/admin/glossaries`
- `PUT /api/admin/glossaries/{id}`
- `DELETE /api/admin/glossaries/{id}`
- `GET /api/admin/translation-jobs`
- `GET /api/admin/translation-jobs/{job}`
- `GET /api/admin/ai-invocations`
- `GET /api/admin/system/translation-provider`
- `PUT /api/admin/system/translation-provider`

详细字段说明见 [`docs/api/translation-admin-api.zh-CN.md`](./docs/api/translation-admin-api.zh-CN.md)。

## 目录结构

```text
caphub-dev/
├── app/
├── bootstrap/
├── config/
├── database/
├── docker/
├── docs/
├── public/
├── resources/
├── routes/
├── tests/
├── compose.yaml
├── Dockerfile
├── composer.json
└── phpunit.xml
```

## 环境配置

默认 `.env.example` 走轻量本地模式：

- `DB_CONNECTION=sqlite`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `REDIS_HOST=127.0.0.1`

翻译相关环境变量：

```dotenv
OPENCLAW_BASE_URL=
OPENCLAW_API_KEY=
OPENCLAW_TRANSLATION_AGENT=github-copilot/gpt-5-mini
OPENCLAW_TIMEOUT=45
OPENCLAW_RETRY_TIMES=1
OPENCLAW_HTML_PARALLELISM=2

HERMES_BASE_URL=
HERMES_API_KEY=
HERMES_PROFILE=chemical-news-translator
HERMES_MODEL=gpt-5-mini
HERMES_TIMEOUT=120
HERMES_HTML_PARALLELISM=2
```

## 启动方式

### 方式 A：本地轻量模式

适合快速开发 API、联调页面和跑测试。

```bash
cd caphub-dev
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

`composer run dev` 会并行启动：

- `php artisan serve`
- `php artisan queue:listen --tries=1 --timeout=0`
- `php artisan pail --timeout=0`
- `npm run dev`

### 方式 B：Laravel Sail 容器模式

适合完整联调、异步任务验证和接近部署环境的开发。

```bash
cd caphub-dev
composer install
cp .env.example .env
./vendor/bin/sail up -d --build
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

`compose.yaml` 当前会启动：

- `app`
- `queue`
- `mysql`
- `redis`

如果使用 Sail，通常需要在 `.env` 中至少补齐这些配置：

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PORT=6379

APP_PORT=8090
FORWARD_DB_PORT=3306
FORWARD_REDIS_PORT=6379
VITE_PORT=5179
```

## 测试

运行全部后端测试：

```bash
cd caphub-dev
composer test
```

运行指定测试：

```bash
php artisan test tests/Feature
php artisan test tests/Unit
```

## 常看文件

- 路由定义：[`routes/api.php`](./routes/api.php)
- 业务代码：[`app/`](./app)
- 数据库迁移：[`database/migrations`](./database/migrations)
- 测试：[`tests/`](./tests)
- 后端 API 文档：[`docs/api/translation-admin-api.zh-CN.md`](./docs/api/translation-admin-api.zh-CN.md)
- 设计与计划：[`docs/superpowers/`](./docs/superpowers)
