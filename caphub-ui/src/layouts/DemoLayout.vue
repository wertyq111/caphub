<script setup>
import PublicHeader from '../components/public/PublicHeader.vue';
import '../styles/neural-prism.css';
</script>

<template>
  <div class="neural-prism relative min-h-screen overflow-hidden text-[var(--np-on-surface)]">
    <!-- Cyberpunk background mesh -->
    <div class="np-bg-mesh" />

    <div class="relative z-10">
      <PublicHeader />

      <main class="mx-auto max-w-[1600px] px-4 py-6 md:px-6 lg:px-8">
        <slot />
      </main>

      <footer class="mt-8 border-t border-[var(--np-outline-variant)]">
        <div class="mx-auto flex max-w-[1600px] flex-col gap-4 px-6 py-5 text-sm text-[var(--np-on-surface-variant)] md:flex-row md:items-center md:justify-between md:px-8">
          <div>CapHub · AI 化工资讯翻译控制矩阵</div>
          <div class="opacity-60">Neural Prism Design System v1.0</div>
        </div>
      </footer>
    </div>
  </div>
</template>
