import { mount, RouterLinkStub } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import { useAdminLocaleStore } from '../../../stores/adminLocale';

describe('AdminLayout', () => {
  let pinia;
  let router;

  beforeEach(async () => {
    if (typeof localStorage?.clear === 'function') {
      localStorage.clear();
    }

    pinia = createPinia();
    setActivePinia(pinia);

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/admin/dashboard',
          component: { template: '<div />' },
          meta: {
            layout: 'admin',
            titleKey: 'routes.dashboard.title',
            descriptionKey: 'routes.dashboard.description',
          },
        },
        {
          path: '/admin/jobs',
          component: { template: '<div />' },
          meta: {
            layout: 'admin',
            titleKey: 'routes.jobs.title',
            descriptionKey: 'routes.jobs.description',
          },
        },
      ],
    });

    await router.push('/admin/jobs');
    await router.isReady();
  });

  it('renders chinese by default and switches the admin shell to english', async () => {
    const wrapper = mount(AdminLayout, {
      slots: {
        default: '<div>admin-body</div>',
      },
      global: {
        plugins: [router],
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    });

    const localeStore = useAdminLocaleStore();

    expect(localeStore.locale).toBe('zh-CN');
    expect(wrapper.text()).toContain('CapHub 后台');
    expect(wrapper.text()).toContain('控制台');
    expect(wrapper.text()).toContain('术语库中心');
    expect(wrapper.text()).toContain('翻译任务');
    expect(wrapper.text()).toContain('调用日志');
    expect(wrapper.text()).toContain('追踪任务生命周期、语言方向和结果输出。');
    expect(wrapper.text()).toContain('admin-body');

    await wrapper.get('[data-locale="en"]').trigger('click');
    await nextTick();

    expect(localeStore.locale).toBe('en');
    expect(wrapper.text()).toContain('CapHub Admin');
    expect(wrapper.text()).toContain('Dashboard');
    expect(wrapper.text()).toContain('Glossary Center');
    expect(wrapper.text()).toContain('Translation Jobs');
    expect(wrapper.text()).toContain('Invocation Logs');
    expect(wrapper.text()).toContain('Track job lifecycle, language direction, and output availability.');
  });
});
