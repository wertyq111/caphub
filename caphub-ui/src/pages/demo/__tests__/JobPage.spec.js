import { ref } from 'vue';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import JobPage from '../JobPage.vue';

const { push, useJobPolling, submitAsyncTranslation } = vi.hoisted(() => ({
  push: vi.fn(),
  useJobPolling: vi.fn(),
  submitAsyncTranslation: vi.fn(),
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

vi.mock('../../../api/translation', () => ({
  submitAsyncTranslation,
}));

describe('JobPage', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    push.mockReset();
    useJobPolling.mockReset();
    submitAsyncTranslation.mockReset();
  });

  afterEach(() => {
    vi.runOnlyPendingTimers();
    vi.useRealTimers();
  });

  it('renders source content, animated translated content, and opens the result view', async () => {
    useJobPolling.mockReturnValue({
      isLoading: ref(false),
      isError: ref(false),
      error: ref(null),
      data: ref({
        status: 'succeeded',
        input_type: 'plain_text',
        document_type: 'chemical_news',
        source_lang: 'zh',
        target_lang: 'en',
        translation_provider: 'github_models',
        translation_agent: 'gpt-4o',
        started_at: '2026-04-21T02:00:00.000Z',
        finished_at: '2026-04-21T02:00:05.000Z',
        source_document: {
          text: '乙烯价格上涨。',
        },
        translated_document: {
          text: 'Ethylene prices are rising.',
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

    vi.runAllTimers();
    await flushPromises();

    expect(wrapper.text()).toContain('翻译正文');
    expect(wrapper.text()).toContain('翻译后内容');
    expect(wrapper.text()).toContain('当前干活的 Agent');
    expect(wrapper.text()).toContain('Copilot');
    expect(wrapper.text()).toContain('gpt-4o');
    expect(wrapper.text()).toContain('乙烯价格上涨。');
    expect(wrapper.text()).toContain('Ethylene prices are rising.');
    expect(wrapper.text()).toContain('整体进度');
    expect(wrapper.text()).toContain('查看翻译结果');

    await wrapper.get('button.np-btn-cta').trigger('click');
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/demo/results/job-uuid-demo');
  });

  it('shows retry action and resubmits the failed job with original content', async () => {
    submitAsyncTranslation.mockResolvedValue({
      job_uuid: 'job-uuid-retry',
      status: 'pending',
    });

    useJobPolling.mockReturnValue({
      isLoading: ref(false),
      isError: ref(false),
      error: ref(null),
      data: ref({
        status: 'failed',
        input_type: 'article_payload',
        document_type: 'chemical_news',
        source_lang: 'zh',
        target_lang: 'en',
        translation_provider: 'hermes',
        translation_agent: 'chemical-news-translator',
        started_at: null,
        finished_at: null,
        source_document: {
          title: '标题',
          summary: '摘要',
          body: '正文',
        },
        translated_document: {},
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
    expect(wrapper.text()).toContain('重新翻译');
    expect(wrapper.text()).toContain('上游接口超时。');
    expect(wrapper.text()).toContain('Hermes');
    expect(wrapper.text()).toContain('chemical-news-translator');

    const retryButton = wrapper.findAll('button').find(button => button.text().includes('重新翻译'));
    expect(retryButton).toBeTruthy();

    await retryButton.trigger('click');
    await flushPromises();

    expect(submitAsyncTranslation).toHaveBeenCalledWith({
      input_type: 'article_payload',
      document_type: 'chemical_news',
      source_lang: 'zh',
      target_lang: 'en',
      content: {
        title: '标题',
        summary: '摘要',
        body: '正文',
      },
    });
    expect(push).toHaveBeenCalledWith('/demo/jobs/job-uuid-retry');
  });
});
