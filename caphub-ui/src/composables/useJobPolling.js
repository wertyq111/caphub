import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import http from '../api/http';

/**
 * 轮询异步翻译任务状态，参数：jobUuidRef 任务 UUID 响应式对象，enabledRef 是否启用轮询。
 * @since 2026-04-02
 * @author zhouxufeng
 */
export function useJobPolling(jobUuidRef, enabledRef) {
  const enabled = computed(() => Boolean(enabledRef?.value) && Boolean(jobUuidRef?.value));

  return useQuery({
    queryKey: computed(() => ['translation-job', jobUuidRef?.value]),
    queryFn: async () => {
      const { data } = await http.get(`/demo/translate/jobs/${jobUuidRef.value}`);
      return data;
    },
    enabled,
    refetchInterval: (query) => {
      if (!query.state.data) {
        return 1500;
      }

      const status = query.state.data.status;
      if (status === 'succeeded' || status === 'failed' || status === 'cancelled') {
        return false;
      }

      return 1500;
    },
  });
}
