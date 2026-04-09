<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import AdminLanguageSwitch from './AdminLanguageSwitch.vue';
import { useAdminI18n } from '../../composables/useAdminI18n';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t } = useAdminI18n();

const pageTitle = computed(() => (
  route.meta.titleKey ? t(route.meta.titleKey, route.meta.title ?? t('common.admin')) : (route.meta.title ?? t('common.admin'))
));
const pageDescription = computed(() => (
  route.meta.descriptionKey
    ? t(route.meta.descriptionKey, route.meta.description ?? t('shell.workspace'))
    : (route.meta.description ?? t('shell.workspace'))
));
const userLabel = computed(() => auth.user?.name || auth.user?.email || t('shell.administrator'));
const userEmail = computed(() => auth.user?.email || 'admin@example.com');

function logout() {
  auth.logout();
  router.push('/admin/login');
}
</script>

<template>
  <header class="flex flex-col gap-5 border-b border-slate-200 bg-white px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
    <div>
      <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-[0.24em] text-slate-400">
        <span>{{ t('common.admin') }}</span>
        <span>/</span>
        <span>{{ pageTitle }}</span>
      </div>
      <h2 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">{{ pageTitle }}</h2>
      <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ pageDescription }}</p>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3">
      <AdminLanguageSwitch />

      <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
      <div class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-500/10 text-sm font-semibold text-sky-700">
        {{ userLabel.slice(0, 1).toUpperCase() }}
      </div>
      <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-slate-900">{{ userLabel }}</p>
        <p class="truncate text-xs text-slate-500">{{ userEmail }}</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-sky-200 hover:text-sky-700"
        @click="logout"
      >
        {{ t('common.signOut') }}
      </button>
    </div>
    </div>
  </header>
</template>
