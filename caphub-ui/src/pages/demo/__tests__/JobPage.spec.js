import { computed, ref } from 'vue';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import JobPage from '../JobPage.vue';

const { push, useJobPolling } = vi.hoisted(() => ({
  push: vi.fn(),
  useJobPolling: vi.fn(),
}));

vi.mock('vue-router', async () => {
  const actual = await vi.importActual('vue-router');

  return {
    ...actual,
    useRoute: () => ({
      params: {
        jobId: 'job-uuid-demo',
      },
    }),
    useRouter: () => ({ push }),
    RouterLink: actual.RouterLink,
  };
});

vi.mock('../../../composables/useJobPolling', () => ({
  useJobPolling,
}));

describe('JobPage', () => {
  beforeEach(() => {
    push.mockReset();
    useJobPolling.mockReset();
  });

  it('renders a localized completed job page and opens the result view', async () => {
    useJobPolling.mockReturnValue({
      isLoading: ref(false),
      isError: ref(false),
      error: ref(null),
      data: ref({
        status: 'succeeded',
        input_type: 'plain_text',
        source_lang: 'zh',
        target_lang: 'en',
        started_at: '2026-04-21T02:00:00.000Z',
        finished_at: '2026-04-21T02:00:05.000Z',
      }),
    });

    const wrapper = mount(JobPage, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
          AppLoader: true,
          AppErrorState: true,
        },
      },
    });

    expect(wrapper.text()).toContain('翻译任务追踪');
    expect(wrapper.text()).toContain('任务概览');
    expect(wrapper.text()).toContain('已完成');
    expect(wrapper.text()).toContain('纯文本');
    expect(wrapper.text()).toContain('zh → en');
    expect(wrapper.text()).toContain('整体进度');
    expect(wrapper.text()).toContain('查看翻译结果');

    await wrapper.get('button.np-btn-cta').trigger('click');
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/demo/results/job-uuid-demo');
  });

  it('renders the failure reason when the job fails', () => {
    useJobPolling.mockReturnValue({
      isLoading: ref(false),
      isError: ref(false),
      error: ref(null),
      data: ref({
        status: 'failed',
        input_type: 'article_payload',
        source_lang: 'zh',
        target_lang: 'en',
        started_at: null,
        finished_at: null,
        error: {
          reason: '上游接口超时。',
        },
      }),
    });

    const wrapper = mount(JobPage, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
          AppLoader: true,
          AppErrorState: true,
        },
      },
    });

    expect(wrapper.text()).toContain('失败原因');
    expect(wrapper.text()).toContain('上游接口超时。');
    expect(wrapper.text()).toContain('JSON 文本');
  });
});
