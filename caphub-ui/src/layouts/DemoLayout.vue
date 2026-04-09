<script setup>
import PublicHeader from '../components/public/PublicHeader.vue';
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-[#020817] text-slate-100">
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute left-[-12rem] top-[-10rem] h-[28rem] w-[28rem] rounded-full bg-sky-500/18 blur-3xl" />
      <div class="absolute right-[-8rem] top-[8rem] h-[22rem] w-[22rem] rounded-full bg-blue-600/14 blur-3xl" />
      <div class="absolute bottom-[-10rem] left-1/2 h-[24rem] w-[24rem] -translate-x-1/2 rounded-full bg-cyan-400/8 blur-3xl" />
      <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,0.72),rgba(2,6,23,0.96))]" />
    </div>

    <div class="relative z-10">
      <PublicHeader />

      <main class="mx-auto max-w-7xl px-6 py-10 md:px-8 md:py-12">
        <slot />
      </main>

      <footer class="border-t border-white/8">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-6 text-sm text-slate-400 md:flex-row md:items-center md:justify-between md:px-8">
          <div>CapHub public demo for AI-powered utility workflows.</div>
          <div class="text-slate-500">Homepage, translation workbench, job tracking, result review.</div>
        </div>
      </footer>
    </div>
  </div>
</template>
