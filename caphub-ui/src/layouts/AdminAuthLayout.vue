<script setup>
import { ElConfigProvider } from 'element-plus';
import { useAdminI18n } from '../composables/useAdminI18n';

const { elementLocale } = useAdminI18n();
</script>

<template>
  <el-config-provider :locale="elementLocale">
    <div
      class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(37,99,235,0.18),transparent_35%),radial-gradient(circle_at_bottom_right,rgba(15,23,42,0.18),transparent_28%),linear-gradient(180deg,#eff4ff_0%,#f7f9fc_52%,#eef2ff_100%)] px-4 py-6 text-slate-900 sm:px-6 lg:px-8"
    >
      <slot />
    </div>
  </el-config-provider>
</template>
