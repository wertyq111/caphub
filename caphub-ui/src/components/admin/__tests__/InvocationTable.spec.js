import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import InvocationTable from '../InvocationTable.vue';
import { useAdminI18n } from '../../../composables/useAdminI18n';

const ElTableStub = {
  props: ['data'],
  template: '<div class="el-table-stub"><slot /></div>',
};

const ElTableColumnStub = {
  props: ['label'],
  computed: {
    row() {
      return this.$parent?.data?.[0] ?? {};
    },
  },
  template: `
    <div class="el-table-column-stub">
      <span class="column-label">{{ label }}</span>
      <slot :row="row" />
    </div>
  `,
};

describe('InvocationTable', () => {
  beforeEach(() => {
    const store = {};
    vi.stubGlobal('localStorage', {
      getItem: vi.fn((key) => store[key] ?? null),
      setItem: vi.fn((key, value) => { store[key] = value; }),
      removeItem: vi.fn((key) => { delete store[key]; }),
      clear: vi.fn(() => { Object.keys(store).forEach((key) => delete store[key]); }),
    });
    localStorage.setItem('caphub_admin_locale', 'zh-CN');
    useAdminI18n().setLocale('zh-CN');
  });

  afterEach(() => {
    localStorage.clear();
    useAdminI18n().setLocale('zh-CN');
  });

  it('renders text bytes and keeps the localized byte label', () => {
    const wrapper = mount(InvocationTable, {
      props: {
        rows: [{
          id: 2373,
          agent_name: 'chemical-news-translator',
          status: 'succeeded',
          duration_ms: 17600,
          text_bytes: 4096,
          created_at: '2026-04-15T07:12:56.000Z',
        }],
      },
      global: {
        stubs: {
          'el-table': ElTableStub,
          'el-table-column': ElTableColumnStub,
          'el-tag': { template: '<span><slot /></span>' },
        },
      },
    });

    expect(wrapper.text()).toContain('文本字节数');
    expect(wrapper.text()).toContain('2373');
    expect(wrapper.text()).toContain('4,096 B');
    expect(wrapper.text()).toContain('17.6s');
  });
});
