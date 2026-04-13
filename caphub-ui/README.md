# CapHub UI

`caphub-ui` 是 CapHub 的 Vue 3 前端，负责公开演示页、翻译工作台、异步任务结果展示和后台管理界面。

## 技术栈

- Vue `^3.5`
- Vite
- Vue Router
- Pinia
- `@tanstack/vue-query`
- Element Plus
- Tailwind CSS 4
- Vitest + Vue Test Utils

## 页面结构

### Demo 页面

- `/`
- `/demo/translate`
- `/demo/jobs/:jobId`
- `/demo/results/:jobId`

### Admin 页面

- `/admin/login`
- `/admin/dashboard`
- `/admin/glossaries`
- `/admin/jobs`
- `/admin/jobs/:jobId`
- `/admin/invocations`

## 目录结构

```text
caphub-ui/
├── public/
├── src/
│   ├── api/
│   ├── components/
│   ├── composables/
│   ├── layouts/
│   ├── pages/
│   ├── router/
│   ├── stores/
│   ├── styles/
│   └── utils/
├── compose.yaml
├── Dockerfile
├── index.html
├── package.json
└── vite.config.js
```

## 环境变量

前端 API 基址通过 `VITE_API_BASE_URL` 控制。

本地开发时，代码中的默认值是：

```text
http://127.0.0.1:8090/api
```

如果你的后端不在这个地址，请显式设置：

```bash
VITE_API_BASE_URL=http://127.0.0.1:8090/api
```

容器模式也建议显式传入 `VITE_API_BASE_URL`，避免使用环境特定的默认值。

## 启动方式

### 方式 A：本地模式

```bash
cd caphub-ui
npm install
npm run dev
```

默认开发地址：

- `http://127.0.0.1:5173`

### 方式 B：容器模式

```bash
cd caphub-ui
docker compose up --build
```

`compose.yaml` 默认把宿主机端口映射到：

- `${CAPHUB_UI_PORT:-5188}:5173`

如果要联调后端，建议一起传入：

```bash
CAPHUB_UI_PORT=5188 \
VITE_API_BASE_URL=http://127.0.0.1:8090/api \
docker compose up --build
```

## 测试

运行前端测试：

```bash
cd caphub-ui
npm run test
```

构建生产包：

```bash
npm run build
```

## 常看文件

- 路由：[`src/router/index.js`](./src/router/index.js)
- API 封装：[`src/api/`](./src/api)
- 页面：[`src/pages/`](./src/pages)
- 组件：[`src/components/`](./src/components)
- 状态管理：[`src/stores/`](./src/stores)
- 样式入口：[`src/styles/`](./src/styles)
