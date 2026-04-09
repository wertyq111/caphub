<script setup>
defineProps({
  message: {
    type: String,
    default: 'Something went wrong.',
  },
});
</script>

<template>
  <div class="rounded-[1.4rem] border border-rose-300/20 bg-rose-400/10 p-4 text-sm text-rose-100 shadow-[0_10px_30px_rgba(244,63,94,0.12)]">
    {{ message }}
  </div>
</template>
