<script setup>
import { useAdminI18n } from '../../composables/useAdminI18n';

const { locale, setLocale } = useAdminI18n();

const options = [
  {
    value: 'zh-CN',
    label: '中文',
  },
  {
    value: 'en',
    label: 'EN',
  },
];
</script>

<template>
  <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
    <button
      v-for="option in options"
      :key="option.value"
      :data-locale="option.value"
      type="button"
      class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
      :class="
        locale === option.value
          ? 'bg-white text-slate-900 shadow-sm'
          : 'text-slate-500 hover:text-slate-900'
      "
      @click="setLocale(option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
