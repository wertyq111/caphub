import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TranslatePage from '../TranslatePage.vue';

const { push, submitSyncTranslation, submitAsyncTranslation } = vi.hoisted(() => ({
  push: vi.fn(),
  submitSyncTranslation: vi.fn(),
  submitAsyncTranslation: vi.fn(),
}));

vi.mock('vue-router', async () => {
  const actual = await vi.importActual('vue-router');
  return {
    ...actual,
    useRouter: () => ({ push }),
  };
});

vi.mock('../../../api/translation', () => ({
  submitSyncTranslation,
  submitAsyncTranslation,
}));

describe('TranslatePage', () => {
  beforeEach(() => {
    push.mockReset();
    submitSyncTranslation.mockReset();
    submitAsyncTranslation.mockReset();
  });

  it('submits with the sync translation API and renders the result inline', async () => {
    submitSyncTranslation.mockResolvedValue({
      translated_document: {
        text: 'Ethylene prices are rising.',
      },
      glossary_hits: [],
      risk_flags: [],
    });

    const wrapper = mount(TranslatePage, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
          TranslationInputPanel: {
            props: ['translationResult'],
            template: `
              <div>
                <button data-test="submit" @click="$emit('submit', payload)">submit</button>
                <div v-if="translationResult">{{ translationResult.translated_document.text }}</div>
              </div>
            `,
            data() {
              return {
                payload: {
                  input_type: 'plain_text',
                  document_type: 'chemical_news',
                  source_lang: 'zh',
                  target_lang: 'en',
                  content: {
                    text: '乙烯价格上涨。',
                  },
                },
              };
            },
          },
          CapabilitySidebar: true,
          AppErrorState: {
            props: ['message'],
            template: '<div data-test="error">{{ message }}</div>',
          },
        },
      },
    });

    await wrapper.get('[data-test="submit"]').trigger('click');
    await flushPromises();

    expect(submitSyncTranslation).toHaveBeenCalledWith({
      input_type: 'plain_text',
      document_type: 'chemical_news',
      source_lang: 'zh',
      target_lang: 'en',
      content: {
        text: '乙烯价格上涨。',
      },
    });
    expect(submitAsyncTranslation).not.toHaveBeenCalled();
    expect(push).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Ethylene prices are rising.');
  });

  it('submits long plain text with the async translation api and jumps to the job page', async () => {
    submitAsyncTranslation.mockResolvedValue({
      job_uuid: 'job-uuid-123',
      status: 'pending',
    });

    const wrapper = mount(TranslatePage, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
          TranslationInputPanel: {
            template: `
              <div>
                <button data-test="submit" @click="$emit('submit', payload)">submit</button>
              </div>
            `,
            data() {
              return {
                payload: {
                  input_type: 'plain_text',
                  document_type: 'chemical_news',
                  source_lang: 'zh',
                  target_lang: 'en',
                  content: {
                    text: '中'.repeat(1801),
                  },
                },
              };
            },
          },
          CapabilitySidebar: true,
          AppErrorState: {
            props: ['message'],
            template: '<div data-test="error">{{ message }}</div>',
          },
        },
      },
    });

    await wrapper.get('[data-test="submit"]').trigger('click');
    await flushPromises();

    expect(submitAsyncTranslation).toHaveBeenCalledWith({
      input_type: 'plain_text',
      document_type: 'chemical_news',
      source_lang: 'zh',
      target_lang: 'en',
      content: {
        text: '中'.repeat(1801),
      },
    });
    expect(submitSyncTranslation).not.toHaveBeenCalled();
    expect(push).toHaveBeenCalledWith('/demo/jobs/job-uuid-123');
  });

  it('lets short plain text explicitly create an async job from the page entry', async () => {
    submitAsyncTranslation.mockResolvedValue({
      job_uuid: 'job-uuid-manual-async',
      status: 'pending',
    });

    const wrapper = mount(TranslatePage, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
          TranslationInputPanel: {
            template: `
              <div>
                <button data-test="submit-async" @click="$emit('submit', payload)">submit async</button>
              </div>
            `,
            data() {
              return {
                payload: {
                  input_type: 'plain_text',
                  document_type: 'chemical_news',
                  source_lang: 'zh',
                  target_lang: 'en',
                  content: {
                    text: '乙烯价格上涨。',
                  },
                  preferred_route: 'async',
                },
              };
            },
          },
          CapabilitySidebar: true,
          AppErrorState: {
            props: ['message'],
            template: '<div data-test="error">{{ message }}</div>',
          },
        },
      },
    });

    await wrapper.get('[data-test="submit-async"]').trigger('click');
    await flushPromises();

    expect(submitAsyncTranslation).toHaveBeenCalledWith({
      input_type: 'plain_text',
      document_type: 'chemical_news',
      source_lang: 'zh',
      target_lang: 'en',
      content: {
        text: '乙烯价格上涨。',
      },
    });
    expect(submitSyncTranslation).not.toHaveBeenCalled();
    expect(push).toHaveBeenCalledWith('/demo/jobs/job-uuid-manual-async');
  });
});
