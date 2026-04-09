import http from './http';

/**
 * 提交同步翻译请求，参数：payload 翻译输入数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function submitSyncTranslation(payload) {
  const { data } = await http.post('/demo/translate/sync', payload);
  return data;
}

/**
 * 提交异步翻译请求，参数：payload 翻译输入数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function submitAsyncTranslation(payload) {
  const { data } = await http.post('/demo/translate/async', payload);
  return data;
}

/**
 * 查询翻译任务状态，参数：jobId 任务 UUID。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function fetchTranslationJob(jobId) {
  const { data } = await http.get(`/demo/translate/jobs/${jobId}`);
  return data;
}

/**
 * 查询翻译任务结果，参数：jobId 任务 UUID。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function fetchTranslationResult(jobId) {
  const { data } = await http.get(`/demo/translate/jobs/${jobId}/result`);
  return data;
}
