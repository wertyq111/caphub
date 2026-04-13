import http from './http';

/**
 * 查询术语表列表，参数：params 查询参数（分页、筛选）。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function fetchGlossaries(params = {}) {
  const { data } = await http.get('/admin/glossaries', { params });
  return data;
}

/**
 * 创建术语表记录，参数：payload 术语表表单数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function createGlossary(payload) {
  const { data } = await http.post('/admin/glossaries', payload);
  return data;
}

/**
 * 更新术语表记录，参数：id 术语 ID，payload 更新数据。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function updateGlossary(id, payload) {
  const { data } = await http.put(`/admin/glossaries/${id}`, payload);
  return data;
}

/**
 * 删除术语表记录，参数：id 术语 ID。
 * @since 2026-04-09
 * @author zhouxufeng
 */
export async function deleteGlossary(id) {
  await http.delete(`/admin/glossaries/${id}`);
}

/**
 * 查询翻译任务列表，参数：params 查询参数（分页、筛选）。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function fetchTranslationJobs(params = {}) {
  const { data } = await http.get('/admin/translation-jobs', { params });
  return data;
}

/**
 * 查询翻译任务详情，参数：jobId 任务 ID。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function fetchTranslationJobDetail(jobId) {
  const { data } = await http.get(`/admin/translation-jobs/${jobId}`);
  return data;
}

/**
 * 查询 AI 调用日志列表，参数：params 查询参数（分页、筛选）。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export async function fetchAiInvocations(params = {}) {
  const { data } = await http.get('/admin/ai-invocations', { params });
  return data;
}

/**
 * 查询当前后台启用的翻译接口提供方。
 * @since 2026-04-13
 * @author zhouxufeng
 */
export async function fetchTranslationProvider() {
  const { data } = await http.get('/admin/system/translation-provider');
  return data;
}

/**
 * 更新后台启用的翻译接口提供方。
 * @since 2026-04-13
 * @author zhouxufeng
 */
export async function updateTranslationProvider(payload) {
  const { data } = await http.put('/admin/system/translation-provider', payload);
  return data;
}
