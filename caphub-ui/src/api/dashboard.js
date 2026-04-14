import http from './http';

/**
 * 获取首页仪表盘数据：代理状态、翻译吞吐量、最近日志。
 * @since 2026-04-13
 */
export async function fetchDashboardStats() {
  const { data } = await http.get('/demo/dashboard/stats');
  return data;
}

/**
 * 发送消息到 Hermes 进行通用对话。
 * @since 2026-04-13
 */
export async function sendChatMessage(message, history = []) {
  const { data } = await http.post('/demo/chat', { message, history }, { timeout: 120_000 });
  return data;
}
