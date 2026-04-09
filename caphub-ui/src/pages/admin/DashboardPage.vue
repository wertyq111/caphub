<script setup>
import { useQuery } from '@tanstack/vue-query';
import { fetchGlossaries, fetchTranslationJobs, fetchAiInvocations } from '../../api/admin';

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
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-3xl font-semibold">Admin Dashboard</h1>
    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">Glossaries</p>
        <p class="text-2xl font-semibold">{{ glossaryQuery.data.value?.total ?? '-' }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">Translation Jobs</p>
        <p class="text-2xl font-semibold">{{ jobsQuery.data.value?.total ?? '-' }}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-500">AI Invocations</p>
        <p class="text-2xl font-semibold">{{ invocationsQuery.data.value?.total ?? '-' }}</p>
      </div>
    </div>
  </div>
</template>
