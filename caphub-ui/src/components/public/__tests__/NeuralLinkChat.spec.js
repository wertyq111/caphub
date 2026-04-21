/**
 * @vitest-environment jsdom
 */
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia } from 'pinia';
import NeuralLinkChat from '../NeuralLinkChat.vue';
import { sendChatMessage } from '../../../api/dashboard';

vi.mock('../../../api/dashboard', () => ({
  sendChatMessage: vi.fn(),
}));

describe('NeuralLinkChat', () => {
  beforeEach(() => {
    const store = {
      caphub_admin_token: 'token',
      caphub_admin_user: JSON.stringify({ id: 1, name: 'Admin' }),
    };

    vi.stubGlobal('localStorage', {
      getItem: vi.fn((key) => store[key] ?? null),
      setItem: vi.fn((key, val) => { store[key] = val; }),
      removeItem: vi.fn((key) => { delete store[key]; }),
      clear: vi.fn(() => { Object.keys(store).forEach(k => delete store[k]); }),
    });

    vi.mocked(sendChatMessage).mockReset();
  });

  it('shows the server reply when chat request fails', async () => {
    vi.mocked(sendChatMessage).mockRejectedValueOnce({
      response: {
        data: {
          reply: '对话助手未返回内容，请稍后重试。',
        },
      },
    });

    const wrapper = mount(NeuralLinkChat, {
      global: {
        plugins: [createPinia()],
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    });

    await wrapper.find('input').setValue('你好');
    const sendButton = wrapper.findAll('button').find((button) => button.text() === '➤');

    expect(sendButton).toBeTruthy();

    await sendButton.trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('对话助手未返回内容，请稍后重试。');
  });
});
