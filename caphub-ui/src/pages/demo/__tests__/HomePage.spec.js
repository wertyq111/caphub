import { mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HomePage from '../HomePage.vue';

describe('HomePage', () => {
  it('renders the matrix hub hero and translation entry', () => {
    const wrapper = mount(HomePage, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    });

    expect(wrapper.text()).toContain('AI 控制矩阵');
    expect(wrapper.text()).toContain('翻译中心');
    expect(wrapper.text()).toContain('系统核心');
  });
});
