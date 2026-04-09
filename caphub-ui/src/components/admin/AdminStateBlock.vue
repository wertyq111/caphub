<script setup>
const props = defineProps({
  mode: {
    type: String,
    default: 'empty',
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
});

const toneClasses = {
  empty: 'border-slate-200 bg-slate-50/80 text-slate-500',
  error: 'border-rose-200 bg-rose-50 text-rose-700',
  loading: 'border-sky-200 bg-sky-50 text-sky-700',
};

const indicatorClasses = {
  empty: 'bg-slate-200',
  error: 'bg-rose-400',
  loading: 'bg-sky-400 animate-pulse',
};
</script>

<template>
  <div
    class="flex min-h-[240px] flex-col items-center justify-center rounded-[24px] border border-dashed px-6 py-10 text-center"
    :class="toneClasses[props.mode] ?? toneClasses.empty"
  >
    <span class="mb-4 h-3 w-3 rounded-full" :class="indicatorClasses[props.mode] ?? indicatorClasses.empty" />
    <h3 class="text-base font-semibold">{{ title }}</h3>
    <p v-if="description" class="mt-2 max-w-md text-sm leading-6">
      {{ description }}
    </p>
    <div v-if="$slots.default" class="mt-5">
      <slot />
    </div>
  </div>
</template>
