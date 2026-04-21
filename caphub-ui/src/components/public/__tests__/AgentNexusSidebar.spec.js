import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AgentNexusSidebar from '../AgentNexusSidebar.vue';

describe('AgentNexusSidebar', () => {
  it('hides the github models card from the public home page', () => {
    const wrapper = mount(AgentNexusSidebar, {
      props: {
        loading: false,
        agents: [
          {
            key: 'openclaw',
            name: 'OpenClaw',
            configured: true,
            active: true,
            stats_24h: { total_calls: 8, avg_latency_ms: 900 },
          },
          {
            key: 'github_models',
            name: 'GitHub Models',
            configured: true,
            active: false,
            stats_24h: { total_calls: 10, avg_latency_ms: 500 },
          },
          {
            key: 'hermes',
            name: 'Hermes',
            configured: true,
            active: false,
            stats_24h: { total_calls: 2, avg_latency_ms: 1400 },
          },
        ],
      },
    });

    expect(wrapper.text()).toContain('在线节点: 2/2');
    expect(wrapper.text()).toContain('OpenClaw');
    expect(wrapper.text()).toContain('Hermes');
    expect(wrapper.text()).not.toContain('GitHub Models');
  });
});
