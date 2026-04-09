<script setup>
import { adminLocaleOptions, useAdminI18n } from '../../composables/useAdminI18n';

const { locale, setLocale, t } = useAdminI18n();
</script>

<template>
  <div class="inline-flex items-center rounded-full border border-slate-200 bg-white/90 p-1 shadow-sm shadow-slate-900/5">
    <button
      v-for="option in adminLocaleOptions"
      :key="option.value"
      type="button"
      class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
      :class="
        locale === option.value
          ? 'bg-slate-950 text-white shadow-sm'
          : 'text-slate-500 hover:text-slate-900'
      "
      @click="setLocale(option.value)"
    >
      {{ t(option.labelKey) }}
    </button>
  </div>
</template>
