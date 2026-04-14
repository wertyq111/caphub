<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AgentNexusSidebar from '../../components/public/AgentNexusSidebar.vue';
import SystemConsoleCards from '../../components/public/SystemConsoleCards.vue';
import SystemPulseSidebar from '../../components/public/SystemPulseSidebar.vue';
import NeuralLinkChat from '../../components/public/NeuralLinkChat.vue';
import { fetchDashboardStats } from '../../api/dashboard';

const loading = ref(true);
const agents = ref([]);
const throughput = ref([]);
const recentLogs = ref([]);
const jobs24h = ref({ total: 0, succeeded: 0, processing: 0 });
const activeProvider = ref('');

let pollTimer = null;

async function loadStats() {
  try {
    const data = await fetchDashboardStats();
    agents.value = data.agents ?? [];
    throughput.value = data.throughput ?? [];
    recentLogs.value = data.recent_logs ?? [];
    jobs24h.value = data.jobs_24h ?? { total: 0, succeeded: 0, processing: 0 };
    activeProvider.value = data.agents?.find(a => a.active)?.key ?? '';
  } catch {
    // Use fallback static data when API is unavailable
    agents.value = [
      {
        key: 'openclaw',
        name: 'OpenClaw',
        configured: true,
        active: true,
        stats_24h: { total_calls: 0, avg_latency_ms: 0, succeeded: 0, failed: 0 },
      },
      {
        key: 'hermes',
        name: 'Hermes',
        configured: false,
        active: false,
        stats_24h: { total_calls: 0, avg_latency_ms: 0, succeeded: 0, failed: 0 },
      },
    ];
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  loadStats();
  pollTimer = setInterval(loadStats, 30000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
  <div class="space-y-5">
    <!-- Main 3-column grid -->
    <div class="grid gap-5 lg:grid-cols-[260px_minmax(0,1fr)_280px]">
      <!-- Left: Agent Nexus -->
      <AgentNexusSidebar :agents="agents" :loading="loading" />

      <!-- Center: Console + Chat -->
      <div class="space-y-5">
        <SystemConsoleCards :loading="loading" :active-provider="activeProvider" />
        <NeuralLinkChat />
      </div>

      <!-- Right: System Pulse -->
      <SystemPulseSidebar
        :throughput="throughput"
        :recent-logs="recentLogs"
        :jobs24h="jobs24h"
        :loading="loading"
      />
    </div>
  </div>
</template>
