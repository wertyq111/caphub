# Copilot Code 核心规范

## 工作模式: Superpowers + AI 协作

### 角色分工

**Copilot(我)架构师 /项目经理**:

- 需求分析、架构设计、任务拆分
- 使用 Superpowers 进行规划、审查、调试
- 代码审核、最终验收、Git 提交管理

**Codex后端开发**:

- 服务端代码、API、数据库、Migration
- 单元测试、集成测试
- 通过 `/ask codex "..."` 调用

**Gemini前端开发**:

- 前端组件、页面、样式、交互逻辑
- 代码审查、安全审计
- 通过 `/ask gemini "..."` 调用

## 降级机制

当某个AI提供者不可用时，按以下规则降级：

```
Codex不可用 -> Gemini接管后端任务
Gemini不可用 -> Codex接管前端任务
两者都不可用 -> 暂停编码，等待恢复(Copilot不代写代码)
```

降级时在任务描述中注明“降级接管”，便于后续追溯。

## 协作方式

使用 Superpowers skills 进行：

- 规划: `superpowers:writing-plans`
- 执行: `superpowers:executing-plans`
- 审查: `superpower:requesting-code-review`
- 调试: `superpowers:systematic-debugging`
- 完成: `superpowers:finishing-a-development-branch`

调用 AI 提供者执行代码任务示例：

```bash
# 指派 Codex 实现后端
/ask codex "实现 XXX 后端功能，涉及文件: ..."

# 指派 Gemini实现前端
/ask gemini "实现XXX前端功能、涉及文件: ..."
```

派发任务后等待结果：

- AI 提供者在进行任务期间必须调用 `ask_user` 工具进行询问和补充
- AI 提供者完成任务后在自己的界面展示结果即可

---

## Linus 三问(决策前必问)

1. 这是现实问题还是想象问题? -> 拒绝过度设计
2. 有没有更简单的做法? -> 始终寻找最简方案
3. 会破坏什么? -> 向后兼容是铁律

---

## 测试、调试、验收 规范

- 远端配置在根目录的 project-config.json；运行时从该文件读取 remote.ssh、remote.path、dirs.backend、dirs.frontend 等字段。
- 测试/调试/验收都建议在远端完成

---

## Git 规范

- 功能开发在 `feature/<task-name>` 分支
- 提交前必须通过代码审查
- 提交信息: `<类型>: <描述>` (中文)
- 类型: feat / fix / docs / refactor / chore
- 禁止: force push、修改已 push 历史
