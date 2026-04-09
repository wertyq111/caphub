<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
});
</script>

<template>
  <section class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_18px_50px_rgba(15,23,42,0.06)]">
    <header v-if="title || description" class="mb-5 flex flex-col gap-2 border-b border-slate-100 pb-4 md:flex-row md:items-end md:justify-between">
      <div>
        <h3 v-if="title" class="text-lg font-semibold text-slate-950">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-sm leading-6 text-slate-500">{{ description }}</p>
      </div>
      <slot name="actions" />
    </header>

    <slot />
  </section>
</template>
