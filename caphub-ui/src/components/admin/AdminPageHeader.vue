<script setup>
defineProps({
  eyebrow: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <section class="rounded-[28px] border border-slate-200/80 bg-white/90 p-6 shadow-sm shadow-slate-900/5">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
      <div class="max-w-3xl">
        <p
          v-if="eyebrow"
          class="text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400"
        >
          {{ eyebrow }}
        </p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">
          {{ title }}
        </h1>
        <p v-if="subtitle" class="mt-2 text-sm leading-6 text-slate-500">
          {{ subtitle }}
        </p>
      </div>
      <div
        v-if="$slots.actions"
        class="flex w-full shrink-0 items-center gap-3 lg:w-auto lg:justify-end"
      >
        <slot name="actions" />
      </div>
    </div>
  </section>
</template>
