<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { fetchTranslationJobDetail } from '../../api/admin';

const route = useRoute();
const jobId = computed(() => String(route.params.jobId ?? ''));

const jobQuery = useQuery({
  queryKey: computed(() => ['admin', 'job-detail', jobId.value]),
  queryFn: () => fetchTranslationJobDetail(jobId.value),
  enabled: computed(() => jobId.value.length > 0),
});
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-3xl font-semibold">Job Detail</h1>
    <el-descriptions border :column="1" v-if="jobQuery.data.value">
      <el-descriptions-item label="ID">{{ jobQuery.data.value.id }}</el-descriptions-item>
      <el-descriptions-item label="Job UUID">{{ jobQuery.data.value.job_uuid }}</el-descriptions-item>
      <el-descriptions-item label="Status">{{ jobQuery.data.value.status }}</el-descriptions-item>
      <el-descriptions-item label="Source Language">{{ jobQuery.data.value.source_lang }}</el-descriptions-item>
      <el-descriptions-item label="Target Language">{{ jobQuery.data.value.target_lang }}</el-descriptions-item>
      <el-descriptions-item label="Mode">{{ jobQuery.data.value.mode }}</el-descriptions-item>
    </el-descriptions>
  </div>
</template>
