<script setup>
import hermesAvatar from '../../assets/brands/hermes-avatar.png';

defineProps({
  providerKey: {
    type: String,
    default: '',
  },
  sizeClass: {
    type: String,
    default: 'h-7 w-7',
  },
});
</script>

<template>
  <svg
    v-if="providerKey === 'openclaw'"
    :class="['openclaw-mark', sizeClass]"
    viewBox="0 0 120 120"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <path
      d="M60 10C30 10 15 35 15 55C15 75 30 95 45 100L45 110H55V100C55 100 60 102 65 100V110H75V100C90 95 105 75 105 55C105 35 90 10 60 10Z"
      fill="url(#openclaw-gradient)"
      class="claw-body"
    />
    <path
      d="M20 45C5 40 0 50 5 60C10 70 20 65 25 55C28 48 25 45 20 45Z"
      fill="url(#openclaw-gradient)"
      class="claw-left"
    />
    <path
      d="M100 45C115 40 120 50 115 60C110 70 100 65 95 55C92 48 95 45 100 45Z"
      fill="url(#openclaw-gradient)"
      class="claw-right"
    />
    <path d="M45 15Q35 5 30 8" stroke="#ff6b73" stroke-width="2.5" stroke-linecap="round" class="antenna" />
    <path d="M75 15Q85 5 90 8" stroke="#ff6b73" stroke-width="2.5" stroke-linecap="round" class="antenna" />
    <circle cx="45" cy="35" r="6" fill="#050810" />
    <circle cx="75" cy="35" r="6" fill="#050810" />
    <circle cx="46" cy="34" r="2.2" fill="#00e5cc" class="eye-glow" />
    <circle cx="76" cy="34" r="2.2" fill="#00e5cc" class="eye-glow" />
    <defs>
      <linearGradient id="openclaw-gradient" x1="10%" y1="5%" x2="100%" y2="95%">
        <stop offset="0%" stop-color="#ffb4bf" />
        <stop offset="100%" stop-color="#ff4d57" />
      </linearGradient>
    </defs>
  </svg>
  <img
    v-else-if="providerKey === 'hermes'"
    :src="hermesAvatar"
    alt="Hermes"
    :class="['hermes-mark object-contain', sizeClass]"
  />
  <div
    v-else
    :class="['fallback-mark flex items-center justify-center rounded-full bg-[rgba(153,247,255,0.12)] text-[var(--np-primary)]', sizeClass]"
  >
    ⬡
  </div>
</template>

<style scoped>
.openclaw-mark {
  animation: openclaw-float 4s ease-in-out infinite;
  filter: drop-shadow(0 0 10px rgba(255, 96, 120, 0.28));
}

.openclaw-mark .eye-glow {
  animation: openclaw-blink 3s ease-in-out infinite;
}

.openclaw-mark .antenna {
  animation: openclaw-wiggle 2s ease-in-out infinite;
  transform-origin: center;
}

.openclaw-mark .claw-left {
  animation: openclaw-snap-left 4s ease-in-out infinite;
  transform-origin: 25% 45%;
}

.openclaw-mark .claw-right {
  animation: openclaw-snap-right 4s ease-in-out 0.2s infinite;
  transform-origin: 75% 45%;
}

.hermes-mark {
  filter: drop-shadow(0 0 8px rgba(95, 195, 255, 0.25));
  image-rendering: -webkit-optimize-contrast;
}

@keyframes openclaw-float {
  0%, 100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-2px);
  }
}

@keyframes openclaw-blink {
  0%, 90%, 100% {
    opacity: 1;
  }

  95% {
    opacity: 0.35;
  }
}

@keyframes openclaw-wiggle {
  0%, 100% {
    transform: rotate(0deg);
  }

  25% {
    transform: rotate(-2deg);
  }

  75% {
    transform: rotate(2deg);
  }
}

@keyframes openclaw-snap-left {
  0%, 85%, 100% {
    transform: rotate(0deg);
  }

  90% {
    transform: rotate(-6deg);
  }

  95% {
    transform: rotate(0deg);
  }
}

@keyframes openclaw-snap-right {
  0%, 85%, 100% {
    transform: rotate(0deg);
  }

  90% {
    transform: rotate(6deg);
  }

  95% {
    transform: rotate(0deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .openclaw-mark,
  .openclaw-mark .eye-glow,
  .openclaw-mark .antenna,
  .openclaw-mark .claw-left,
  .openclaw-mark .claw-right {
    animation: none;
  }
}
</style>
