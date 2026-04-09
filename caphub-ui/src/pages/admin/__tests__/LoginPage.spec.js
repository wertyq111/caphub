import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import ElementPlus from 'element-plus';
import LoginPage from '../LoginPage.vue';
import { useAdminLocaleStore } from '../../../stores/adminLocale';

vi.mock('vue-router', async () => {
  const actual = await vi.importActual('vue-router');
  return {
    ...actual,
    useRouter: () => ({
      push: vi.fn(),
    }),
  };
});

describe('LoginPage', () => {
  let pinia;

  beforeEach(() => {
    if (typeof localStorage?.clear === 'function') {
      localStorage.clear();
    }

    pinia = createPinia();
    setActivePinia(pinia);
  });

  it('shows chinese admin copy by default and updates to english after switching locale', async () => {
    const wrapper = mount(LoginPage, {
      global: {
        plugins: [pinia, ElementPlus],
      },
    });

    const localeStore = useAdminLocaleStore();

    expect(localeStore.locale).toBe('zh-CN');
    expect(wrapper.text()).toContain('后台登录');
    expect(wrapper.text()).toContain('翻译运营与术语治理后台工作台');
    expect(wrapper.text()).toContain('登录');

    localeStore.setLocale('en');
    await nextTick();

    expect(wrapper.text()).toContain('Admin Login');
    expect(wrapper.text()).toContain('Admin workspace for translation operations and glossary governance.');
    expect(wrapper.text()).toContain('Login');
  });
});
