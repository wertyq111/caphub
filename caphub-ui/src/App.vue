<script setup>
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import DemoLayout from './layouts/DemoLayout.vue';
import AdminLayout from './layouts/AdminLayout.vue';
import AdminAuthLayout from './layouts/AdminAuthLayout.vue';

const route = useRoute();

const layoutComponent = computed(() => {
  if (route.meta.layout === 'admin-auth') {
    return AdminAuthLayout;
  }

  if (route.meta.layout === 'admin') {
    return AdminLayout;
  }

  return DemoLayout;
});
</script>

<template>
  <component :is="layoutComponent">
    <RouterView />
  </component>
</template>
