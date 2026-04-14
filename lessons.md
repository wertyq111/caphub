# Lessons

- 用户明确纠偏后，先回到需求本身，不要为了压耗时私自加“少字走特殊接口”这类分流规则。
- 同步翻译变慢先查上游接口真实耗时、重试、返回格式和缓存命中，不要先改业务路径掩盖问题。
- 不要把 OpenClaw 的 `translation_agent`、底层 `model` 和返回里的 `provider_model` 混成一件事；当前仓库里 OpenClaw 传的是 `chemical-news-translator`，不是 `github-copilot/gpt-5-mini`。
- 改 OpenClaw 默认 agent 后，先绕开缓存看真实请求，再看上游返回的 `provider_model`，不要只盯配置文件。
