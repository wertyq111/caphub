<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useJobPolling } from '../../composables/useJobPolling';
import JobTimeline from '../../components/demo/JobTimeline.vue';
import AppLoader from '../../components/shared/AppLoader.vue';
import AppErrorState from '../../components/shared/AppErrorState.vue';

const route = useRoute();
const router = useRouter();

const jobId = computed(() => String(route.params.jobId ?? ''));
const pollingEnabled = computed(() => jobId.value.length > 0);
const jobQuery = useJobPolling(jobId, pollingEnabled);

/**
 * 跳转到翻译结果页，参数：无。
 * @since 2026-04-02
 * @author zhouxufeng
 */
function goToResult() {
  router.push(`/demo/results/${jobId.value}`);
}
</script>

<template>
  <div class="space-y-6">
    <div class="space-y-3">
      <p class="text-xs font-medium uppercase tracking-[0.3em] text-sky-200/70">Job Tracking</p>
      <h1 class="text-3xl font-semibold text-white">Translation Job</h1>
      <p class="break-all text-sm text-slate-300">Job UUID: {{ jobId }}</p>
    </div>

    <AppLoader v-if="jobQuery.isLoading.value" />
    <AppErrorState
      v-else-if="jobQuery.isError.value"
      :message="jobQuery.error.value?.message ?? 'Failed to load job status.'"
    />
    <template v-else>
      <JobTimeline :status="jobQuery.data.value?.status ?? 'pending'" />
      <div class="rounded-[1.7rem] border border-white/10 bg-white/5 p-5">
        <p class="text-sm text-slate-200">Current status: {{ jobQuery.data.value?.status }}</p>
        <button
          v-if="jobQuery.data.value?.status === 'succeeded'"
          class="mt-3 rounded-full bg-sky-400 px-4 py-2 text-sm font-medium text-slate-950 shadow-[0_12px_32px_rgba(56,189,248,0.28)] transition hover:-translate-y-0.5 hover:bg-sky-300"
          @click="goToResult"
        >
          View Result
        </button>
      </div>
    </template>
  </div>
</template>
