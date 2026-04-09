import { mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FeatureCard from '../FeatureCard.vue';

describe('FeatureCard', () => {
  it('renders a router link for available features', () => {
    const wrapper = mount(FeatureCard, {
      props: {
        feature: {
          title: '智能翻译工作台',
          description: 'desc',
          meta: 'meta',
          to: '/demo/translate',
          icon: '译',
          available: true,
        },
      },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    });

    expect(wrapper.findComponent(RouterLinkStub).props('to')).toBe('/demo/translate');
  });

  it('renders a static article for unavailable features', () => {
    const wrapper = mount(FeatureCard, {
      props: {
        feature: {
          title: '术语治理中心',
          description: 'desc',
          meta: 'meta',
          icon: '术',
          available: false,
        },
      },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    });

    expect(wrapper.find('article').exists()).toBe(true);
    expect(wrapper.findComponent(RouterLinkStub).exists()).toBe(false);
  });
});
