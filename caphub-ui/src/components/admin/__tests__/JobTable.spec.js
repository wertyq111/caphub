import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import JobTable from '../JobTable.vue';
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

describe('JobTable', () => {
  beforeEach(() => {
    const store = {};
    vi.stubGlobal('localStorage', {
      getItem: vi.fn((key) => store[key] ?? null),
      setItem: vi.fn((key, value) => { store[key] = value; }),
      removeItem: vi.fn((key) => { delete store[key]; }),
      clear: vi.fn(() => { Object.keys(store).forEach((key) => delete store[key]); }),
    });
    localStorage.setItem('caphub_admin_locale', 'en-US');
    useAdminI18n().setLocale('en-US');
  });

  afterEach(() => {
    localStorage.clear();
    useAdminI18n().setLocale('zh-CN');
  });

  it('renders localized timing columns and a formatted duration chip', () => {
    const wrapper = mount(JobTable, {
      props: {
        rows: [{
          id: 1,
          job_uuid: 'job-uuid-1',
          mode: 'sync',
          input_type: 'plain_text',
          status: 'succeeded',
          source_text: '中文翻译',
          started_at: '2026-04-14T10:00:00.000Z',
          finished_at: '2026-04-14T10:00:02.500Z',
        }],
      },
      global: {
        stubs: {
          'el-table': ElTableStub,
          'el-table-column': ElTableColumnStub,
          'el-tag': { template: '<span><slot /></span>' },
          'el-button': { template: '<button><slot /></button>' },
        },
      },
    });

    expect(wrapper.text()).toContain('Duration');
    expect(wrapper.text()).toContain('Started at');
    expect(wrapper.text()).toContain('Finished at');
    expect(wrapper.text()).toContain('2.5s');
    expect(wrapper.text()).not.toContain('ID');
  });
});
