# CapHub

CapHub 是一个面向化工资讯场景的 AI 翻译与术语治理工作区，覆盖 Demo 翻译、异步任务追踪、后台术语治理和 AI 调用审计。

这个仓库不是单体应用说明书，而是一个前后端协作工作区：

- 后端项目：[`caphub-dev/`](./caphub-dev)
- 前端项目：[`caphub-ui/`](./caphub-ui)

## 仓库结构

```text
caphub/
├── README.md
├── AGENTS.md
├── docs/
├── caphub-dev/
└── caphub-ui/
```

## 子项目说明

| 目录 | 技术栈 | 作用 | 文档 |
| --- | --- | --- | --- |
| `caphub-dev` | Laravel 13、PHP 8.3、Sanctum、Queue、MySQL/SQLite、Redis | Demo API、Admin API、异步任务、术语治理、AI 调用审计 | [`caphub-dev/README.md`](./caphub-dev/README.md) |
| `caphub-ui` | Vue 3、Vite、Pinia、Vue Router、Vue Query、Element Plus、Tailwind CSS 4 | 公开演示页、翻译工作台、任务结果页、后台管理界面 | [`caphub-ui/README.md`](./caphub-ui/README.md) |

## 快速指引

### 只看整体

- 先看当前文件：[`README.md`](./README.md)
- 产品/架构设计说明：[`docs/`](./docs) 和 [`caphub-dev/docs/`](./caphub-dev/docs)

### 启动后端

进入 [`caphub-dev/`](./caphub-dev)，看 [`caphub-dev/README.md`](./caphub-dev/README.md)：

- 本地轻量模式：SQLite + `composer run dev`
- 容器模式：Laravel Sail + MySQL + Redis

### 启动前端

进入 [`caphub-ui/`](./caphub-ui)，看 [`caphub-ui/README.md`](./caphub-ui/README.md)：

- 本地模式：`npm install && npm run dev`
- 容器模式：`docker compose up --build`

### 常见联调组合

1. 后端本地轻量模式 + 前端本地模式
2. 后端 Sail 容器模式 + 前端本地模式
3. 后端 Sail 容器模式 + 前端容器模式

## 当前业务范围

### Demo 侧

- 公开首页 `/`
- 同步翻译 `/demo/translate`
- 异步任务状态 `/demo/jobs/:jobId`
- 异步结果页 `/demo/results/:jobId`

### Admin 侧

- 登录 `/admin/login`
- 概览 `/admin/dashboard`
- 术语管理 `/admin/glossaries`
- 任务列表与详情 `/admin/jobs`
- AI 调用日志 `/admin/invocations`

## 文档分工

- 根目录 README：项目总览、工作区导航、阅读顺序
- `caphub-dev/README.md`：后端开发、运行、测试、接口和目录说明
- `caphub-ui/README.md`：前端开发、运行、环境变量、页面和目录说明

如果你要直接开始干活，建议顺序是：

1. 先看根目录 [`README.md`](./README.md)
2. 再进入对应子项目看各自的 README
3. 需要接口字段时看 [`caphub-dev/docs/api/translation-admin-api.zh-CN.md`](./caphub-dev/docs/api/translation-admin-api.zh-CN.md)
