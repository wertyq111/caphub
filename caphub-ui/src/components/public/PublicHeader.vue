<script setup>
import { RouterLink, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const navigationItems = [
  { label: '首页', to: '/', icon: '◈' },
  { label: '翻译演示', to: '/demo/translate', icon: '⬡' },
];

const route = useRoute();
const auth = useAuthStore();
</script>

<template>
  <header class="sticky top-0 z-30 np-glass-strong" style="border-bottom: 1px solid rgba(153,247,255,0.06);">
    <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-6 px-6 py-3 lg:px-8">
      <!-- Brand -->
      <RouterLink to="/" class="flex items-center gap-3 text-white no-underline">
        <div class="relative flex h-10 w-10 items-center justify-center rounded-xl np-glass-feature">
          <span class="np-font-display text-lg font-bold text-[var(--np-primary)]">C</span>
          <div class="absolute inset-0 rounded-xl" style="box-shadow: 0 0 20px rgba(153,247,255,0.15);" />
        </div>
        <div>
          <div class="np-font-display text-[10px] font-semibold uppercase tracking-[0.4em] text-[var(--np-primary)]" style="opacity: 0.8;">CapHub</div>
          <div class="text-xs font-medium text-[var(--np-on-surface-variant)]">AI 控制矩阵</div>
        </div>
      </RouterLink>

      <!-- Navigation -->
      <nav class="hidden items-center gap-1 md:flex">
        <RouterLink
          v-for="item in navigationItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-2 rounded-[var(--np-radius-md)] px-4 py-2 text-sm no-underline transition-all duration-200"
          :class="route.path === item.to
            ? 'np-glass-feature text-[var(--np-primary)]'
            : 'text-[var(--np-on-surface-variant)] hover:text-[var(--np-on-surface)] hover:bg-white/[0.04]'"
        >
          <span class="text-xs">{{ item.icon }}</span>
          {{ item.label }}
        </RouterLink>
      </nav>

      <!-- Right actions -->
      <div class="flex items-center gap-3">
        <!-- Auth status -->
        <template v-if="auth.isAuthenticated">
          <span class="hidden items-center gap-2 text-xs text-[var(--np-on-surface-variant)] sm:flex">
            <span class="np-dot-pulse h-2 w-2 rounded-full bg-[var(--np-success)] text-[var(--np-success)]" />
            {{ auth.user?.name || auth.user?.email || '已登录' }}
          </span>
        </template>
        <RouterLink
          v-else
          to="/admin/login"
          class="np-btn-secondary text-xs no-underline"
        >
          登录
        </RouterLink>

        <!-- CTA -->
        <RouterLink
          to="/admin"
          class="np-btn-cta !px-4 !py-2 !text-sm no-underline"
        >
          后端中心
        </RouterLink>
      </div>
    </div>
  </header>
</template>
