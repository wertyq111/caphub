<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  padded: {
    type: Boolean,
    default: true,
  },
  headerDivider: {
    type: Boolean,
    default: true,
  },
});
</script>

<template>
  <section class="overflow-hidden rounded-[28px] border border-slate-200/80 bg-white shadow-sm shadow-slate-900/5">
    <header
      v-if="title || subtitle || $slots.header || $slots['header-actions']"
      :class="[
        'flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-start lg:justify-between',
        headerDivider ? 'border-b border-slate-200/80' : '',
      ]"
    >
      <div>
        <slot name="header">
          <h2 v-if="title" class="text-lg font-semibold text-slate-950">{{ title }}</h2>
          <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
        </slot>
      </div>
      <div v-if="$slots['header-actions']" class="flex items-center gap-3">
        <slot name="header-actions" />
      </div>
    </header>
    <div :class="padded ? 'p-6' : ''">
      <slot />
    </div>
  </section>
</template>
