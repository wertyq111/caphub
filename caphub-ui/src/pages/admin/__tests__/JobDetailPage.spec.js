import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import JobDetailPage from '../JobDetailPage.vue';

const { fetchTranslationJobDetail } = vi.hoisted(() => ({
  fetchTranslationJobDetail: vi.fn(),
}));

vi.mock('vue-router', async () => {
  const actual = await vi.importActual('vue-router');
  return {
    ...actual,
    useRoute: () => ({
      params: {
        jobId: '42',
      },
    }),
  };
});

vi.mock('../../../api/admin', () => ({
  fetchTranslationJobDetail,
}));

describe('JobDetailPage', () => {
  it('renders translated output, glossary hits, risk flags, and notes from the job result', async () => {
    fetchTranslationJobDetail.mockResolvedValue({
      id: 42,
      job_uuid: 'job-detail-42',
      status: 'succeeded',
      source_lang: 'zh',
      target_lang: 'en',
      mode: 'async',
      document_type: 'chemical_news',
      source_text: '乙烯价格上涨。',
      failure_reason: null,
      result: {
        translated_document_json: {
          text: 'Ethylene prices rose.',
        },
        meta_payload: {
          glossary_hits: [
            {
              source_term: '乙烯',
              chosen_translation: 'ethylene',
            },
          ],
        },
        risk_payload: ['Review the catalyst context.'],
        notes_payload: ['Standardized against petrochemical glossary.'],
      },
    });

    const wrapper = mount(JobDetailPage, {
      global: {
        plugins: [
          createPinia(),
          [VueQueryPlugin, { queryClient: new QueryClient() }],
          ElementPlus,
        ],
      },
    });

    await flushPromises();

    expect(fetchTranslationJobDetail).toHaveBeenCalledWith('42');
    expect(wrapper.text()).toContain('Ethylene prices rose.');
    expect(wrapper.text()).toContain('乙烯');
    expect(wrapper.text()).toContain('ethylene');
    expect(wrapper.text()).toContain('Review the catalyst context.');
    expect(wrapper.text()).toContain('Standardized against petrochemical glossary.');
  });
});
