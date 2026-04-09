# CapHub

CapHub 是一个面向化工资讯场景的 AI 翻译与术语治理项目。它不是通用翻译器，而是围绕“化工新闻翻译、术语命中、风险标记、任务追踪、后台审计”这一条业务链路构建的前后端一体化工作区。

当前仓库包含两个实际运行的子项目：

| 目录 | 技术栈 | 作用 |
| --- | --- | --- |
| `caphub-dev` | Laravel 13、PHP 8.3、Sanctum、Queue、MySQL/SQLite、Redis | 提供 Demo 翻译接口、Admin API、异步任务、术语治理、AI 调用审计 |
| `caphub-ui` | Vue 3、Vite、Pinia、Vue Router、Vue Query、Element Plus、Tailwind CSS 4 | 提供公开演示页、翻译工作台、任务结果页和后台管理界面 |

## 当前能力

### Demo 能力

- 公开首页 `/`
- 同步翻译工作台 `/demo/translate`
- 异步任务状态页 `/demo/jobs/:jobId`
- 异步结果查看页 `/demo/results/:jobId`
- 支持两类输入：
  - `plain_text`
  - `article_payload`（标题、摘要、正文）
- 返回结果包含翻译正文、术语命中、风险标记等结构化信息

### Admin 能力

- 登录 `/admin/login`
- 概览面板 `/admin/dashboard`
- 术语表管理 `/admin/glossaries`
- 翻译任务列表与详情 `/admin/jobs`、`/admin/jobs/:jobId`
- AI 调用日志 `/admin/invocations`
- 使用 Laravel Sanctum Bearer Token 进行接口鉴权

### 后端域模型

后端当前已经落地的核心实体包括：

- `translation_jobs`
- `translation_results`
- `glossaries`
- `glossary_aliases`
- `glossary_forbidden_translations`
- `translation_glossary_hits`
- `ai_invocations`
- `demo_access_logs`
- `personal_access_tokens`

## 工作区结构

```text
caphub/
├── README.md
├── AGENTS.md
├── docs/
├── caphub-dev/
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── docs/
│   ├── routes/
│   ├── tests/
│   ├── compose.yaml
│   └── composer.json
└── caphub-ui/
    ├── public/
    ├── src/
    │   ├── api/
    │   ├── components/
    │   ├── layouts/
    │   ├── pages/
    │   ├── router/
    │   └── stores/
    ├── compose.yaml
    └── package.json
```

## 前后端对应关系

### 前端页面

- `/`：品牌首页与系统展示入口
- `/demo/translate`：同步翻译工作台，当前页面默认走同步翻译接口
- `/demo/jobs/:jobId`：异步任务轮询与状态展示
- `/demo/results/:jobId`：异步任务结果、术语命中与风险标记展示
- `/admin/login`：后台登录
- `/admin/dashboard`：后台概览
- `/admin/glossaries`：术语管理
- `/admin/jobs`：任务列表
- `/admin/jobs/:jobId`：任务详情
- `/admin/invocations`：AI 调用日志

### 后端 API

基础前缀：`/api`

#### Demo API

- `GET /api/ping`
- `POST /api/demo/translate/sync`
- `POST /api/demo/translate/async`
- `GET /api/demo/translate/jobs/{jobUuid}`
- `GET /api/demo/translate/jobs/{jobUuid}/result`

#### Admin API

- `POST /api/admin/login`
- `GET /api/admin/glossaries`
- `POST /api/admin/glossaries`
- `PUT /api/admin/glossaries/{id}`
- `DELETE /api/admin/glossaries/{id}`
- `GET /api/admin/translation-jobs`
- `GET /api/admin/translation-jobs/{job}`
- `GET /api/admin/ai-invocations`

详细字段和响应结构见：

- [`caphub-dev/docs/api/translation-admin-api.zh-CN.md`](caphub-dev/docs/api/translation-admin-api.zh-CN.md)

## 技术栈

### 后端 `caphub-dev`

- PHP `^8.3`
- Laravel `^13`
- Laravel Sanctum
- Laravel Sail
- MySQL 8.4
- Redis
- SQLite（轻量本地模式可用）
- Pest / PHPUnit

### 前端 `caphub-ui`

- Vue `^3.5`
- Vue Router `^4`
- Pinia `^3`
- `@tanstack/vue-query`
- Element Plus
- Tailwind CSS 4
- Vitest + Vue Test Utils

## 开发方式

项目当前实际支持两套后端开发方式：

1. 推荐的容器化方式：Laravel Sail + MySQL + Redis
2. 轻量本地方式：SQLite + `composer run dev`

### 方式 A：后端推荐使用 Laravel Sail

适合完整联调、异步任务验证和接近部署环境的开发。

```bash
cd caphub-dev
composer install
cp .env.example .env
```

如果你要走 Sail 容器栈，`.env` 至少需要补齐或调整以下配置：

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PORT=6379

APP_PORT={自定义后端端口}
FORWARD_DB_PORT={自定义数据库端口}
FORWARD_REDIS_PORT={自定义redis端口}
VITE_PORT=5179

OPENCLAW_BASE_URL=
OPENCLAW_API_KEY=
OPENCLAW_TRANSLATION_AGENT=chemical-news-translator
OPENCLAW_TIMEOUT=30
```

然后启动：

```bash
cd caphub-dev
./vendor/bin/sail up -d --build
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

Sail 模式下，`compose.yaml` 会启动这些服务：

- `app`
- `queue`
- `mysql`
- `redis`

默认对外端口通常是：

- 后端 API：`http://127.0.0.1:{自定义后端端口}`
- MySQL：`127.0.0.1:{自定义数据库端口}`
- Redis：`127.0.0.1:{自定义redis端口}`

### 方式 B：后端轻量本地模式

适合快速开发 API、页面联调或跑单元测试，不依赖 MySQL/Redis 容器。

`.env.example` 当前默认就是这套思路：

- `DB_CONNECTION=sqlite`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `REDIS_HOST=127.0.0.1`

启动方式：

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

### 前端本地开发

裸机运行前端时，Vite 默认端口来自脚本本身：

```bash
cd caphub-ui
npm install
npm run dev
```

默认地址：

- 前端：`http://127.0.0.1:5173`

前端代码里的默认 API 基址是：

```text
http://127.0.0.1:{自定义后端端口}/api
```

也就是说，如果你是“前端裸机 + 后端 Sail”的组合，通常不需要额外改 `VITE_API_BASE_URL`。

### 前端容器化开发

前端也提供了单独的 `compose.yaml`：

```bash
cd caphub-ui
docker compose up --build
```

该方式默认：

- 暴露前端端口 `{自定义前端端口} -> 5173`
- 通过环境变量注入 `VITE_API_BASE_URL`
- 未手动覆盖时，默认指向 `http://{服务器地址}:{自定义后端端口}/api`

因此：

- 本地裸机开发，优先使用 `npm run dev`
- 远端协作或统一容器入口时，再使用前端 `compose.yaml`

## 环境变量约定

### 后端关键环境变量

| 变量 | 说明 |
| --- | --- |
| `OPENCLAW_BASE_URL` | 上游翻译服务地址 |
| `OPENCLAW_API_KEY` | 上游翻译服务密钥 |
| `OPENCLAW_TRANSLATION_AGENT` | 当前翻译代理名，默认 `chemical-news-translator` |
| `OPENCLAW_TIMEOUT` | 调用超时时间（秒） |
| `APP_PORT` | 后端对外暴露端口 |
| `FORWARD_DB_PORT` | MySQL 映射端口 |
| `FORWARD_REDIS_PORT` | Redis 映射端口 |

### 前端关键环境变量

| 变量 | 说明 |
| --- | --- |
| `VITE_API_BASE_URL` | API 基址，默认回退到 `http://127.0.0.1:{自定义后端端口}/api` |
| `CAPHUB_UI_PORT` | 前端容器对外端口，默认 `{自定义前端端口}` |

## 默认账号与登录说明

后端 `DatabaseSeeder` 当前只会创建一个测试用户：

- `test@example.com`

前端登录页会默认预填：

- `admin@example.com`
- `password`

这组凭据在测试中被使用，但不会由 `db:seed` 自动创建。如果你需要手动补一个管理员用户，可以执行：

```bash
cd caphub-dev
php artisan tinker
```

```php
use App\Models\User;

User::updateOrCreate(
    ['email' => 'admin@example.com'],
    ['name' => 'Admin', 'password' => 'password'],
);
```

## 测试

### 后端

优先使用 Sail：

```bash
cd caphub-dev
./vendor/bin/sail artisan test
```

如果当前没有起 Sail，也可以使用项目脚本：

```bash
cd caphub-dev
composer test
```

后端测试覆盖重点包括：

- Admin 登录
- 术语 CRUD
- 翻译任务列表
- Demo 同步翻译
- Demo 异步翻译任务流
- OpenClaw 客户端与基础设施接口

### 前端

```bash
cd caphub-ui
npm test
```

也可以跑指定测试：

```bash
cd caphub-ui
npm test -- src/utils/__tests__/adminPresentation.spec.js
```

## 远端联调与验收

当前协作默认以远端 Docker 环境作为最终验收环境。

远端主机：

```bash
ssh ubuntu@{服务器地址}
```

远端目录：

- 工作区：`{项目根目录}`
- 后端：`{项目根目录}/caphub-dev`
- 前端：`{项目根目录}/caphub-ui`

常用访问地址：

- 前端：`http://{服务器地址}:{自定义前端端口}/`
- 后端 API：`http://{服务器地址}:{自定义后端端口}/api`

建议的远端验证顺序：

1. 先删除本地 `._*` 文件，避免同步脏文件。
2. 连接远端主机。
3. 进入对应项目目录。
4. 将本地修改同步到远端目录。
5. 在远端目录中执行验证命令。
6. 后端验证优先使用 Sail 命令。

## 建议优先阅读的文件

- [`README.md`](README.md)
- [`caphub-dev/routes/api.php`](caphub-dev/routes/api.php)
- [`caphub-dev/docs/api/translation-admin-api.zh-CN.md`](caphub-dev/docs/api/translation-admin-api.zh-CN.md)
- [`caphub-dev/app/Services/Translation/TranslationService.php`](caphub-dev/app/Services/Translation/TranslationService.php)
- [`caphub-ui/src/router/index.js`](caphub-ui/src/router/index.js)
- [`caphub-ui/src/pages/demo/TranslatePage.vue`](caphub-ui/src/pages/demo/TranslatePage.vue)
- [`caphub-ui/src/pages/admin/LoginPage.vue`](caphub-ui/src/pages/admin/LoginPage.vue)
- [`caphub-ui/src/api/http.js`](caphub-ui/src/api/http.js)

## README 的定位

这份根目录 README 主要负责回答：

- 这个仓库里有哪些项目
- 前后端分别承担什么职责
- 本地和容器化各自应该怎么启动
- 当前页面、API、测试和联调入口在哪里

更细的 API 字段、产品设计、实现计划和专项文档，建议继续放在各子项目自己的 `docs/` 下维护。
