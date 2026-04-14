<script setup>
import PublicHeader from '../components/public/PublicHeader.vue';
import '../styles/neural-prism.css';
</script>

<template>
  <div class="neural-prism relative min-h-screen overflow-hidden text-[var(--np-on-surface)]">
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute left-[-12rem] top-[-10rem] h-[28rem] w-[28rem] rounded-full bg-[rgba(153,247,255,0.06)] blur-[120px]" />
      <div class="absolute right-[-8rem] top-[8rem] h-[22rem] w-[22rem] rounded-full bg-[rgba(172,137,255,0.05)] blur-[100px]" />
      <div class="absolute bottom-[-10rem] left-1/2 h-[24rem] w-[24rem] -translate-x-1/2 rounded-full bg-[rgba(153,247,255,0.03)] blur-[120px]" />
    </div>

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
