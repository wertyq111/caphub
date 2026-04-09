<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { fetchTranslationJobs } from '../../api/admin';
import AdminPageHeader from '../../components/admin/AdminPageHeader.vue';
import AdminPanel from '../../components/admin/AdminPanel.vue';
import AdminStateBlock from '../../components/admin/AdminStateBlock.vue';
import JobTable from '../../components/admin/JobTable.vue';
import { useAdminI18n } from '../../composables/useAdminI18n';
import { resolveRequestError } from '../../utils/adminPresentation';

const router = useRouter();
const { t } = useAdminI18n();

const jobsQuery = useQuery({
  queryKey: ['admin', 'jobs'],
  queryFn: () => fetchTranslationJobs({ per_page: 50 }),
});

const rows = computed(() => jobsQuery.data.value?.data ?? []);
const totalCount = computed(() => jobsQuery.data.value?.total ?? rows.value.length);
const errorMessage = computed(() =>
  jobsQuery.error.value ? resolveRequestError(jobsQuery.error.value, t('states.errorDescription')) : '',
);

/**
 * 打开任务详情页，参数：row 当前选中任务行。
 * @since 2026-04-02
 * @author zhouxufeng
 */
function viewDetail(row) {
  router.push(`/admin/jobs/${row.id}`);
}
</script>

<template>
  <div class="space-y-6">
    <AdminPageHeader
      eyebrow="CapHub"
      :title="t('pages.jobs.title')"
      :subtitle="t('pages.jobs.description')"
    />

    <AdminPanel
      :title="t('jobs.listTitle')"
      :subtitle="t('jobs.listSubtitle')"
      :padded="false"
    >
      <template #header-actions>
        <el-tag round effect="plain">{{ t('common.totalRecords', { count: totalCount }) }}</el-tag>
        <el-button @click="jobsQuery.refetch()">{{ t('common.refresh') }}</el-button>
      </template>

      <JobTable v-if="rows.length" :rows="rows" @view="viewDetail" />
      <AdminStateBlock
        v-else-if="jobsQuery.isPending.value"
        mode="loading"
        :title="t('states.loadingTitle')"
        :description="t('states.loadingDescription')"
      />
      <AdminStateBlock
        v-else-if="jobsQuery.isError.value"
        mode="error"
        :title="t('states.errorTitle')"
        :description="errorMessage"
      >
        <el-button @click="jobsQuery.refetch()">{{ t('common.retry') }}</el-button>
      </AdminStateBlock>
      <AdminStateBlock
        v-else
        mode="empty"
        :title="t('states.emptyTitle')"
        :description="t('states.emptyDescription')"
      />
    </AdminPanel>
  </div>
</template>
