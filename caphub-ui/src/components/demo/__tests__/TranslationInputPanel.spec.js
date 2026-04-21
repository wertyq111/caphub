import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TranslationInputPanel from '../TranslationInputPanel.vue';

describe('TranslationInputPanel', () => {
  it('switches between plain_text and article_payload modes', async () => {
    const wrapper = mount(TranslationInputPanel);

    await wrapper.find('[data-mode="article_payload"]').trigger('click');

    expect(wrapper.emitted('mode-change')[0]).toEqual(['article_payload']);
  });

  it('renders readonly language labels and shows translated text in the right panel', () => {
    const wrapper = mount(TranslationInputPanel, {
      props: {
        translationResult: {
          translated_document: {
            text: 'Ethylene prices are rising.',
          },
        },
      },
    });

    expect(wrapper.text()).toContain('中文');
    expect(wrapper.text()).toContain('英文');
    expect(wrapper.find('input').exists()).toBe(false);
    expect(wrapper.text()).toContain('翻译结果');
    expect(wrapper.text()).toContain('Ethylene prices are rising.');
  });

  it('offers an async translation entry and emits async route intent', async () => {
    const wrapper = mount(TranslationInputPanel);

    const buttons = wrapper.findAll('button');
    const asyncButton = buttons.find(button => button.text().includes('异步翻译'));

    expect(asyncButton).toBeTruthy();

    await asyncButton.trigger('click');

    expect(wrapper.emitted('submit')?.[0]?.[0]).toMatchObject({
      input_type: 'plain_text',
      preferred_route: 'async',
    });
  });
});
