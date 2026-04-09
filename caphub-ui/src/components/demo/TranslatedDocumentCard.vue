<script setup>
const props = defineProps({
  translatedDocument: {
    type: Object,
    default: () => ({}),
  },
});

function hasValue(value) {
  return typeof value === 'string' && value.length > 0;
}
</script>

<template>
  <section class="space-y-3 rounded-[1.7rem] border border-emerald-300/20 bg-emerald-300/10 p-5">
    <h2 class="text-lg font-semibold text-emerald-100">Translated Document</h2>
    <p v-if="hasValue(props.translatedDocument.text)" class="whitespace-pre-wrap text-slate-100">
      {{ props.translatedDocument.text }}
    </p>
    <div v-else class="space-y-2">
      <p v-if="hasValue(props.translatedDocument.title)" class="font-medium text-white">{{ props.translatedDocument.title }}</p>
      <p v-if="hasValue(props.translatedDocument.summary)" class="text-sm text-slate-200">{{ props.translatedDocument.summary }}</p>
      <p v-if="hasValue(props.translatedDocument.body)" class="whitespace-pre-wrap text-slate-100">{{ props.translatedDocument.body }}</p>
      <p
        v-if="!hasValue(props.translatedDocument.title) && !hasValue(props.translatedDocument.summary) && !hasValue(props.translatedDocument.body)"
        class="text-sm text-slate-300"
      >
        No translated content available yet.
      </p>
    </div>
  </section>
</template>
