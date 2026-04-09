<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { fetchAiInvocations } from '../../api/admin';
import InvocationTable from '../../components/admin/InvocationTable.vue';

const invocationsQuery = useQuery({
  queryKey: ['admin', 'invocations'],
  queryFn: () => fetchAiInvocations({ per_page: 50 }),
});

const rows = computed(() => invocationsQuery.data.value?.data ?? []);
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-3xl font-semibold">AI Invocations</h1>
    <InvocationTable :rows="rows" />
  </div>
</template>
