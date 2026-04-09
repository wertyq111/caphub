<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { fetchTranslationJobs } from '../../api/admin';
import JobTable from '../../components/admin/JobTable.vue';

const router = useRouter();

const jobsQuery = useQuery({
  queryKey: ['admin', 'jobs'],
  queryFn: () => fetchTranslationJobs({ per_page: 50 }),
});

const rows = computed(() => jobsQuery.data.value?.data ?? []);

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
  <div class="space-y-4">
    <h1 class="text-3xl font-semibold">Translation Jobs</h1>
    <JobTable :rows="rows" @view="viewDetail" />
  </div>
</template>
