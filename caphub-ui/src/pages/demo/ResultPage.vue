<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useRoute } from 'vue-router';
import { fetchTranslationResult } from '../../api/translation';
import TranslatedDocumentCard from '../../components/demo/TranslatedDocumentCard.vue';
import GlossaryHitsPanel from '../../components/demo/GlossaryHitsPanel.vue';
import RiskFlagsPanel from '../../components/demo/RiskFlagsPanel.vue';
import AppLoader from '../../components/shared/AppLoader.vue';
import AppErrorState from '../../components/shared/AppErrorState.vue';

const route = useRoute();
const jobId = computed(() => String(route.params.jobId ?? ''));

const resultQuery = useQuery({
  queryKey: computed(() => ['translation-result', jobId.value]),
  queryFn: () => fetchTranslationResult(jobId.value),
  enabled: computed(() => jobId.value.length > 0),
});
</script>

<template>
  <div class="space-y-6">
    <div class="space-y-3">
      <p class="text-xs font-medium uppercase tracking-[0.3em] text-sky-200/70">Result Review</p>
      <h1 class="text-3xl font-semibold text-white">Translation Result</h1>
      <p class="break-all text-sm text-slate-300">Job UUID: {{ jobId }}</p>
    </div>

    <AppLoader v-if="resultQuery.isLoading.value" />
    <AppErrorState
      v-else-if="resultQuery.isError.value"
      :message="resultQuery.error.value?.message ?? 'Failed to load translation result.'"
    />
    <template v-else>
      <TranslatedDocumentCard :translated-document="resultQuery.data.value?.translated_document ?? {}" />
      <GlossaryHitsPanel :glossary-hits="resultQuery.data.value?.glossary_hits ?? []" />
      <RiskFlagsPanel :risk-flags="resultQuery.data.value?.risk_flags ?? []" />
    </template>
  </div>
</template>
