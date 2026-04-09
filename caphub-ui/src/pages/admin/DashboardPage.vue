<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { adminNavigationItems } from '../../admin/navigation';
import { fetchGlossaries, fetchTranslationJobs, fetchAiInvocations } from '../../api/admin';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminStateBlock from '../../components/admin/AdminStateBlock.vue';
import { useAdminI18n } from '../../composables/useAdminI18n';
import { resolveRequestError } from '../../utils/adminPresentation';

const { t } = useAdminI18n();

const glossaryQuery = useQuery({
  queryKey: ['admin', 'dashboard', 'glossaries'],
  queryFn: () => fetchGlossaries({ per_page: 1 }),
});

const jobsQuery = useQuery({
  queryKey: ['admin', 'dashboard', 'jobs'],
  queryFn: () => fetchTranslationJobs({ per_page: 1 }),
});

const invocationsQuery = useQuery({
  queryKey: ['admin', 'dashboard', 'invocations'],
  queryFn: () => fetchAiInvocations({ per_page: 1 }),
});

const isInitialLoading = computed(
  () =>
    glossaryQuery.isPending.value &&
    jobsQuery.isPending.value &&
    invocationsQuery.isPending.value,
);

const errorMessage = computed(() => {
  const dashboardError =
    glossaryQuery.error.value || jobsQuery.error.value || invocationsQuery.error.value;

  return dashboardError
    ? resolveRequestError(dashboardError, t('states.errorDescription'))
    : '';
});

const metricCards = computed(() => [
  {
    key: 'glossaries',
    value: glossaryQuery.data.value?.total ?? '--',
    label: t('dashboard.glossaryCount'),
  },
  {
    key: 'jobs',
    value: jobsQuery.data.value?.total ?? '--',
    label: t('dashboard.jobCount'),
  },
  {
    key: 'invocations',
    value: invocationsQuery.data.value?.total ?? '--',
    label: t('dashboard.invocationCount'),
  },
]);

const quickLinks = computed(() =>
  adminNavigationItems.filter((item) => item.routeName !== 'admin-dashboard'),
);
</script>

<template>
  <div class="space-y-6">
    <AdminPageHeader
      eyebrow="CapHub"
      :title="t('pages.dashboard.title')"
      :subtitle="t('pages.dashboard.description')"
    />

    <AdminStateBlock
      v-if="isInitialLoading"
      mode="loading"
      :title="t('states.loadingTitle')"
      :description="t('states.loadingDescription')"
    />

    <template v-else>
      <div class="grid gap-4 md:grid-cols-3">
        <div
          v-for="card in metricCards"
          :key="card.key"
          class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white p-6 shadow-sm shadow-slate-900/5"
        >
          <p class="text-sm font-medium text-slate-500">{{ card.label }}</p>
          <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">{{ card.value }}</p>
          <div class="mt-6 h-2 rounded-full bg-slate-100">
            <div
              class="h-full rounded-full bg-gradient-to-r from-sky-500 to-blue-600"
              :style="{ width: `${card.value === '--' ? 22 : 72}%` }"
            />
          </div>
        </div>
      </div>

      <AdminPanel
        :title="t('dashboard.quickAccessTitle')"
        :subtitle="t('dashboard.quickAccessDescription')"
      >
        <div class="grid gap-4 md:grid-cols-3">
          <RouterLink
            v-for="item in quickLinks"
            :key="item.routeName"
            :to="item.path"
            class="group rounded-[24px] border border-slate-200 bg-slate-50/70 p-5 transition hover:-translate-y-0.5 hover:border-sky-200 hover:bg-sky-50"
          >
            <div
              class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-slate-700 shadow-sm shadow-slate-900/5 transition group-hover:bg-sky-500 group-hover:text-white"
            >
              <el-icon size="18">
                <component :is="item.icon" />
              </el-icon>
            </div>
            <h3 class="mt-4 text-base font-semibold text-slate-950">{{ t(item.labelKey) }}</h3>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ t(item.descriptionKey) }}</p>
          </RouterLink>
        </div>
      </AdminPanel>

      <AdminStateBlock
        v-if="errorMessage"
        mode="error"
        :title="t('states.errorTitle')"
        :description="errorMessage"
      />
    </template>
  </div>
</template>
