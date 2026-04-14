import { mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import HomePage from '../HomePage.vue';

vi.mock('../../../api/dashboard', () => ({
  fetchDashboardStats: vi.fn().mockRejectedValue(new Error('no api')),
  sendChatMessage: vi.fn().mockResolvedValue({ reply: 'ok' }),
}));

describe('HomePage', () => {
  it('renders the matrix hub hero and translation entry', () => {
    const wrapper = mount(HomePage, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    });

    expect(wrapper.text()).toContain('代理网络 Nexus');
    expect(wrapper.text()).toContain('系统核心控制台');
    expect(wrapper.text()).toContain('NEURAL LINK');
    expect(wrapper.text()).toContain('系统脉搏');
  });
});
