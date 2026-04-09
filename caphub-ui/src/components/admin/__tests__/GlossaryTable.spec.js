import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ElementPlus from 'element-plus';
import GlossaryTable from '../GlossaryTable.vue';

describe('GlossaryTable', () => {
  it('renders glossary rows', () => {
    const wrapper = mount(GlossaryTable, {
      props: {
        rows: [{ id: 1, term: 'ethylene', standard_translation: '乙烯' }],
      },
      global: {
        plugins: [ElementPlus],
      },
    });

    expect(wrapper.text()).toContain('ethylene');
    expect(wrapper.text()).toContain('乙烯');
  });
});
