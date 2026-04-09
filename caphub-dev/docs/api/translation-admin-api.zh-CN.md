# Caphub 接口文档（翻译 Demo + Admin）

## 1. 基本说明
- 基础前缀：`/api`
- 数据格式：`application/json`
- 鉴权方式（Admin 接口）：`Authorization: Bearer {token}`（Laravel Sanctum）
- 时间格式：ISO 8601（例如 `2026-04-02T10:00:00+08:00`）

## 2. Demo 接口（无需登录）

### 2.1 健康检查
- 方法与路径：`GET /api/ping`

响应示例：
```json
{
  "ok": true
}
```

### 2.2 同步翻译
- 方法与路径：`POST /api/demo/translate/sync`
- 说明：实时返回翻译结果（受 `throttle:demo-sync-translation` 限流）

请求体：
```json
{
  "input_type": "plain_text",
  "document_type": "chemical_news",
  "source_lang": "zh-CN",
  "target_lang": "en",
  "content": {
    "text": "乙烯价格今日小幅上涨。"
  }
}
```

字段说明：
- `input_type`：`plain_text` 或 `article_payload`
- `document_type`：可选，业务文档类型（如 `chemical_news`）
- `source_lang`：源语言（必填）
- `target_lang`：目标语言（必填）
- `content`：翻译内容对象（必填）
- `content.text`：`input_type=plain_text` 时必填
- `content.title|summary|body`：`input_type=article_payload` 时至少填一个

响应示例（成功）：
```json
{
  "status": "succeeded",
  "input_type": "plain_text",
  "translated_document": {
    "text": "Ethylene prices rose slightly today."
  },
  "glossary_hits": [],
  "risk_flags": [],
  "notes": [],
  "meta": {
    "schema_version": "v1",
    "provider_model": "chemical-news-translator",
    "cache_hit": false,
    "mode": "sync"
  }
}
```

失败示例：
- `422`：参数校验失败
- `502`：翻译调用失败，或上游返回非法 JSON / 目标语言结果未通过内容校验

### 2.3 创建异步翻译任务
- 方法与路径：`POST /api/demo/translate/async`
- 说明：只创建任务并入队，结果通过轮询查询

请求体：与同步翻译一致。

响应示例：
```json
{
  "job_id": 102,
  "job_uuid": "29f3ca7d-6e26-4e1c-befa-cb8e5b8eb2cb",
  "status": "pending"
}
```

状态码：`202 Accepted`

### 2.4 查询异步任务状态
- 方法与路径：`GET /api/demo/translate/jobs/{jobUuid}`

响应示例：
```json
{
  "job_id": 102,
  "job_uuid": "29f3ca7d-6e26-4e1c-befa-cb8e5b8eb2cb",
  "status": "processing",
  "input_type": "article_payload",
  "source_lang": "zh-CN",
  "target_lang": "en",
  "started_at": "2026-04-02T10:10:01+08:00",
  "finished_at": null
}
```

失败任务响应补充示例：
```json
{
  "job_id": 102,
  "job_uuid": "29f3ca7d-6e26-4e1c-befa-cb8e5b8eb2cb",
  "status": "failed",
  "input_type": "plain_text",
  "source_lang": "zh-CN",
  "target_lang": "en",
  "started_at": "2026-04-03T14:20:00+08:00",
  "finished_at": "2026-04-03T14:21:10+08:00",
  "error": {
    "code": "translation_failed",
    "reason": "OpenClaw translated_document key [text] contains Chinese characters for English target output."
  }
}
```

状态说明：
- `pending`：待处理
- `queued`：已入队，等待 Worker 消费
- `processing`：处理中
- `succeeded`：成功
- `failed`：失败
- `cancelled`：取消

### 2.5 查询异步任务结果
- 方法与路径：`GET /api/demo/translate/jobs/{jobUuid}/result`
- 说明：
- 任务成功且已有结果时返回 `200`
- 任务仍在 `pending|queued|processing` 时返回 `202`
- 任务已失败时返回 `409`
- 任务不存在时返回 `404`

响应示例：
```json
{
  "job_id": 102,
  "job_uuid": "29f3ca7d-6e26-4e1c-befa-cb8e5b8eb2cb",
  "status": "succeeded",
  "input_type": "article_payload",
  "translated_document": {
    "title": "Ethylene Market Update",
    "summary": "Prices increased slightly due to supply constraints.",
    "body": "..."
  },
  "glossary_hits": [
    {
      "source_term": "乙烯",
      "chosen_translation": "ethylene"
    }
  ],
  "risk_flags": [],
  "notes": [],
  "meta": {
    "schema_version": "v1",
    "provider_model": "chemical-news-translator",
    "mode": "async"
  }
}
```

任务未完成响应示例（`202 Accepted`）：
```json
{
  "job_id": 102,
  "job_uuid": "29f3ca7d-6e26-4e1c-befa-cb8e5b8eb2cb",
  "status": "processing",
  "input_type": "plain_text",
  "message": "Translation result is not ready yet.",
  "error": {
    "code": "translation_result_not_ready",
    "reason": "The async translation job has not produced a result yet."
  }
}
```

任务失败响应示例（`409 Conflict`）：
```json
{
  "job_id": 102,
  "job_uuid": "29f3ca7d-6e26-4e1c-befa-cb8e5b8eb2cb",
  "status": "failed",
  "input_type": "plain_text",
  "message": "Translation job failed.",
  "error": {
    "code": "translation_failed",
    "reason": "OpenClaw translated_document key [text] contains Chinese characters for English target output."
  }
}
```

## 3. Admin 鉴权接口

### 3.1 登录
- 方法与路径：`POST /api/admin/login`

请求体：
```json
{
  "email": "admin@example.com",
  "password": "secret"
}
```

响应示例：
```json
{
  "token": "1|xxxxxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com"
  }
}
```

失败示例：
- `401`：账号或密码错误
- `422`：参数校验失败

## 4. Admin 业务接口（需 Bearer Token）

### 4.1 术语表列表
- 方法与路径：`GET /api/admin/glossaries?per_page=20`

响应：Laravel 分页结构，`data` 为术语列表。

`data[]` 主要字段：
- `id`
- `term`
- `source_lang`
- `target_lang`
- `standard_translation`
- `domain`
- `priority`
- `status`（`active|inactive`）
- `notes`
- `created_at`
- `updated_at`

### 4.2 创建术语
- 方法与路径：`POST /api/admin/glossaries`

请求体示例：
```json
{
  "term": "乙烯",
  "source_lang": "zh-CN",
  "target_lang": "en",
  "standard_translation": "ethylene",
  "domain": "petrochemical",
  "priority": 100,
  "status": "active",
  "notes": "化工行业标准译法"
}
```

状态码：`201 Created`

### 4.3 更新术语
- 方法与路径：`PUT /api/admin/glossaries/{glossary}`
- 说明：支持部分字段更新

请求体示例：
```json
{
  "standard_translation": "Ethylene",
  "status": "active",
  "notes": "首字母大写"
}
```

### 4.4 翻译任务列表
- 方法与路径：`GET /api/admin/translation-jobs?per_page=20`
- 说明：返回分页任务数据，包含关联 `result`

`data[]` 常用字段：
- `id`
- `job_uuid`
- `mode`
- `status`
- `input_type`
- `source_lang`
- `target_lang`
- `started_at`
- `finished_at`
- `result`（可能为 `null`）

### 4.5 翻译任务详情
- 方法与路径：`GET /api/admin/translation-jobs/{job}`
- 说明：返回单条任务完整数据（包含 `result`）

### 4.6 AI 调用日志列表
- 方法与路径：`GET /api/admin/ai-invocations?per_page=20`
- 说明：返回分页调用日志，包含关联 `translationJob`

`data[]` 常用字段：
- `id`
- `job_id`
- `agent_name`
- `skill_version`
- `request_payload`
- `response_payload_summary`
- `status`
- `duration_ms`
- `token_usage_estimate`
- `error_message`
- `created_at`

## 5. 参数校验规则补充
- `input_type=plain_text` 时：
  - 必须提供 `content.text`
  - 不允许传 `content.title|summary|body` 的非空值
- `input_type=article_payload` 时：
  - 不允许传 `content.text` 的非空值
  - `content.title|summary|body` 至少一项非空
- 登录：
  - `email` 必须是合法邮箱
  - `password` 必填

## 6. 前端对接建议
- Demo 页面：
  - 同步模式：直接调用 `/demo/translate/sync`
  - 异步模式：先调用 `/demo/translate/async`，再轮询 `/demo/translate/jobs/{jobUuid}`，成功后读 `/result`
- Admin 页面：
  - 登录后将 `token` 存本地，并在请求头统一注入 `Authorization: Bearer {token}`
  - 列表页统一使用 `per_page` 控制分页尺寸
