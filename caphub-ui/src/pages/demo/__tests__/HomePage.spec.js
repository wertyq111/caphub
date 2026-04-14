/**
 * @vitest-environment jsdom
 */
import { mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createPinia } from 'pinia';
import HomePage from '../HomePage.vue';

vi.mock('../../../api/dashboard', () => ({
  fetchDashboardStats: vi.fn().mockRejectedValue(new Error('no api')),
  sendChatMessage: vi.fn().mockResolvedValue({ reply: 'ok' }),
}));

describe('HomePage', () => {
  beforeEach(() => {
    // Ensure localStorage is available for auth store
    const store = {};
    vi.stubGlobal('localStorage', {
      getItem: vi.fn((key) => store[key] ?? null),
      setItem: vi.fn((key, val) => { store[key] = val; }),
      removeItem: vi.fn((key) => { delete store[key]; }),
      clear: vi.fn(() => { Object.keys(store).forEach(k => delete store[k]); }),
    });
  });

  it('renders the matrix hub hero and translation entry', () => {
    const wrapper = mount(HomePage, {
      global: {
        plugins: [createPinia()],
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
