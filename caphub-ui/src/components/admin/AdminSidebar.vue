<script setup>
import {
  Connection,
  DocumentCopy,
  Grid,
  Management,
} from '@element-plus/icons-vue';
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAdminI18n } from '../../composables/useAdminI18n';

const route = useRoute();
const { t } = useAdminI18n();

const navItems = [
  {
    key: 'dashboard',
    labelKey: 'routes.dashboard.title',
    to: '/admin/dashboard',
    icon: Grid,
  },
  {
    key: 'glossaries',
    labelKey: 'routes.glossary.title',
    to: '/admin/glossaries',
    icon: Management,
  },
  {
    key: 'jobs',
    labelKey: 'routes.jobs.title',
    to: '/admin/jobs',
    icon: DocumentCopy,
  },
  {
    key: 'invocations',
    labelKey: 'routes.invocations.title',
    to: '/admin/invocations',
    icon: Connection,
  },
];

const activePath = computed(() => route.path);

function isActive(item) {
  return activePath.value === item.to || activePath.value.startsWith(`${item.to}/`);
}
</script>

<template>
  <aside class="flex h-full w-full flex-col bg-slate-950 text-slate-100">
    <div class="border-b border-white/10 px-6 py-6">
      <p class="text-[11px] uppercase tracking-[0.38em] text-sky-200/70">{{ t('shell.operations') }}</p>
      <h1 class="mt-3 text-2xl font-semibold text-white">{{ t('shell.brand') }}</h1>
      <p class="mt-3 text-sm leading-6 text-slate-400">
        {{ t('shell.intro') }}
      </p>
    </div>

    <nav class="flex-1 space-y-2 px-4 py-5">
      <RouterLink
        v-for="item in navItems"
        :key="item.key"
        :to="item.to"
        class="group flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition"
        :class="
          isActive(item)
            ? 'bg-sky-500/18 text-white shadow-[inset_0_0_0_1px_rgba(125,211,252,0.2)]'
            : 'text-slate-400 hover:bg-white/5 hover:text-white'
        "
      >
        <component :is="item.icon" class="h-5 w-5" />
        <span>{{ t(item.labelKey) }}</span>
      </RouterLink>
    </nav>

    <div class="border-t border-white/10 px-6 py-5 text-xs leading-6 text-slate-500">
      {{ t('shell.footer') }}
    </div>
  </aside>
</template>
