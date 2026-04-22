<script setup>
import { computed } from 'vue';

const props = defineProps({
  state: {
    type: String,
    default: 'processing',
    validator: (value) => ['processing', 'success', 'error'].includes(value),
  },
});

const stateClass = computed(() => `job-agent-robot--${props.state}`);
const voiceBars = [0, 1, 2, 3, 4];
</script>

<template>
  <div :class="['job-agent-robot', stateClass]" aria-hidden="true">
    <div class="job-agent-robot__halo" />

    <div class="job-agent-robot__frame">
      <div class="job-agent-robot__signal" />
      <div class="job-agent-robot__antenna" />

      <div class="job-agent-robot__shell">
        <div class="job-agent-robot__screen">
          <template v-if="state === 'processing'">
            <span class="job-agent-robot__eye job-agent-robot__eye--left" />
            <span class="job-agent-robot__eye job-agent-robot__eye--right" />
            <span class="job-agent-robot__scanner" />
          </template>

          <template v-else-if="state === 'success'">
            <span class="job-agent-robot__smile-eye job-agent-robot__smile-eye--left" />
            <span class="job-agent-robot__smile-eye job-agent-robot__smile-eye--right" />
          </template>

          <template v-else>
            <span class="job-agent-robot__cross job-agent-robot__cross--left">x</span>
            <span class="job-agent-robot__cross job-agent-robot__cross--right">x</span>
          </template>
        </div>

        <div class="job-agent-robot__voice">
          <span
            v-for="bar in voiceBars"
            :key="bar"
            class="job-agent-robot__voice-bar"
            :style="{ '--voice-index': bar }"
          />
        </div>
      </div>

      <div class="job-agent-robot__joint" />
      <div class="job-agent-robot__thruster" />
    </div>
  </div>
</template>

<style scoped>
.job-agent-robot {
  --robot-accent: #fbbf24;
  --robot-accent-glow: rgba(251, 191, 36, 0.45);
  --robot-thruster: rgba(251, 191, 36, 0.8);
  --robot-shell: rgba(15, 23, 42, 0.92);
  --robot-shell-edge: rgba(148, 163, 184, 0.12);
  --robot-screen: #04070d;
  --robot-raise: 0px;
  position: relative;
  display: flex;
  width: 96px;
  height: 112px;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.job-agent-robot__halo {
  position: absolute;
  inset: auto 14px 10px;
  height: 22px;
  border-radius: 999px;
  background: radial-gradient(circle, color-mix(in srgb, var(--robot-accent) 30%, transparent) 0%, transparent 72%);
  filter: blur(10px);
  opacity: 0.75;
}

.job-agent-robot__frame {
  position: relative;
  display: flex;
  width: 100%;
  height: 100%;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  transform: translateY(var(--robot-raise));
}

.job-agent-robot__signal {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: var(--robot-accent);
  box-shadow: 0 0 14px var(--robot-accent-glow);
}

.job-agent-robot__antenna {
  width: 3px;
  height: 18px;
  margin-top: -1px;
  border-radius: 999px 999px 2px 2px;
  background: linear-gradient(180deg, color-mix(in srgb, white 45%, var(--robot-accent)) 0%, rgba(125, 211, 252, 0.82) 100%);
  box-shadow: 0 0 0 1px rgba(148, 163, 184, 0.14);
}

.job-agent-robot__shell {
  position: relative;
  width: 54px;
  height: 52px;
  margin-top: 2px;
  border-radius: 14px;
  border: 1px solid var(--robot-shell-edge);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, transparent 18%),
    linear-gradient(180deg, color-mix(in srgb, var(--robot-shell) 90%, black) 0%, var(--robot-shell) 100%);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.06),
    0 18px 28px rgba(15, 23, 42, 0.32),
    0 0 18px color-mix(in srgb, var(--robot-accent) 10%, transparent);
}

.job-agent-robot__shell::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), transparent 38%);
  pointer-events: none;
}

.job-agent-robot__screen {
  position: relative;
  display: flex;
  width: 32px;
  height: 18px;
  margin: 10px auto 0;
  align-items: center;
  justify-content: center;
  gap: 8px;
  overflow: hidden;
  border-radius: 7px;
  border: 1px solid rgba(148, 163, 184, 0.12);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.04) 0%, transparent 30%),
    var(--robot-screen);
  box-shadow: inset 0 0 12px rgba(0, 0, 0, 0.62);
}

.job-agent-robot__eye,
.job-agent-robot__smile-eye,
.job-agent-robot__cross {
  position: relative;
  z-index: 1;
}

.job-agent-robot__eye {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--robot-accent);
  box-shadow: 0 0 10px var(--robot-accent-glow);
}

.job-agent-robot__scanner {
  position: absolute;
  top: 2px;
  left: -40%;
  width: 30%;
  height: 14px;
  border-radius: 999px;
  background: linear-gradient(90deg, transparent 0%, color-mix(in srgb, var(--robot-accent) 55%, transparent) 50%, transparent 100%);
  opacity: 0.65;
}

.job-agent-robot__smile-eye {
  width: 9px;
  height: 5px;
  border-top: 2px solid var(--robot-accent);
  border-radius: 999px 999px 0 0;
  box-shadow: 0 -1px 8px color-mix(in srgb, var(--robot-accent) 38%, transparent);
}

.job-agent-robot__smile-eye--left {
  transform: rotate(15deg);
}

.job-agent-robot__smile-eye--right {
  transform: rotate(-15deg);
}

.job-agent-robot__cross {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 8px;
  color: var(--robot-accent);
  font-size: 10px;
  line-height: 1;
  font-weight: 700;
  text-shadow: 0 0 8px color-mix(in srgb, var(--robot-accent) 55%, transparent);
}

.job-agent-robot__voice {
  display: flex;
  width: 18px;
  height: 10px;
  margin: 10px auto 0;
  align-items: end;
  justify-content: center;
  gap: 2px;
}

.job-agent-robot__voice-bar {
  width: 2px;
  height: 6px;
  border-radius: 999px;
  background: var(--robot-accent);
  box-shadow: 0 0 8px color-mix(in srgb, var(--robot-accent) 45%, transparent);
}

.job-agent-robot__joint {
  width: 26px;
  height: 8px;
  margin-top: 6px;
  border-radius: 0 0 8px 8px;
  background: linear-gradient(180deg, rgba(100, 116, 139, 0.72) 0%, rgba(51, 65, 85, 0.92) 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.job-agent-robot__thruster {
  width: 20px;
  height: 28px;
  margin-top: -2px;
  border-radius: 0 0 999px 999px;
  background: linear-gradient(180deg, var(--robot-thruster) 0%, rgba(245, 158, 11, 0.08) 100%);
  filter: blur(2px);
  opacity: 0.9;
}

.job-agent-robot--processing {
  --robot-accent: #99f6e4;
  --robot-accent-glow: rgba(153, 246, 228, 0.45);
  --robot-thruster: rgba(34, 211, 238, 0.82);
}

.job-agent-robot--success {
  --robot-accent: #34d399;
  --robot-accent-glow: rgba(52, 211, 153, 0.42);
  --robot-thruster: rgba(16, 185, 129, 0.82);
}

.job-agent-robot--error {
  --robot-accent: #fb7185;
  --robot-accent-glow: rgba(251, 113, 133, 0.42);
  --robot-thruster: rgba(251, 113, 133, 0.52);
}

.job-agent-robot--processing .job-agent-robot__frame {
  animation: robot-processing-float 4s ease-in-out infinite;
}

.job-agent-robot--processing .job-agent-robot__signal {
  animation: robot-processing-pulse 1.8s ease-in-out infinite;
}

.job-agent-robot--processing .job-agent-robot__scanner {
  animation: robot-processing-scan 1.35s linear infinite;
}

.job-agent-robot--processing .job-agent-robot__voice-bar {
  animation: robot-processing-voice 1s ease-in-out infinite;
  animation-delay: calc(var(--voice-index) * 0.12s);
}

.job-agent-robot--processing .job-agent-robot__thruster {
  animation: robot-processing-thruster 0.9s ease-in-out infinite;
}

.job-agent-robot--success .job-agent-robot__frame {
  animation: robot-success-bounce 1.05s ease-in-out infinite;
}

.job-agent-robot--success .job-agent-robot__signal {
  animation: robot-success-pulse 0.8s ease-in-out infinite;
}

.job-agent-robot--success .job-agent-robot__voice-bar {
  height: 4px;
  animation: robot-success-voice 1.05s ease-in-out infinite;
}

.job-agent-robot--success .job-agent-robot__thruster {
  animation: robot-success-thruster 0.52s ease-in-out infinite;
}

.job-agent-robot--error .job-agent-robot__frame {
  animation: robot-error-shake 0.4s linear infinite;
}

.job-agent-robot--error .job-agent-robot__signal {
  animation: robot-error-alert 0.15s linear infinite;
}

.job-agent-robot--error .job-agent-robot__voice-bar {
  animation: robot-error-voice 0.12s linear infinite;
}

.job-agent-robot--error .job-agent-robot__thruster {
  animation: robot-error-thruster 0.18s linear infinite;
}

@keyframes robot-processing-float {
  0%,
  100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-8px);
  }
}

@keyframes robot-processing-pulse {
  0%,
  100% {
    opacity: 0.45;
    transform: scale(1);
  }

  50% {
    opacity: 1;
    transform: scale(1.22);
  }
}

@keyframes robot-processing-scan {
  0% {
    left: -35%;
  }

  100% {
    left: 105%;
  }
}

@keyframes robot-processing-voice {
  0%,
  100% {
    height: 4px;
  }

  50% {
    height: 10px;
  }
}

@keyframes robot-processing-thruster {
  0%,
  100% {
    height: 22px;
    opacity: 0.45;
  }

  50% {
    height: 34px;
    opacity: 0.92;
  }
}

@keyframes robot-success-bounce {
  0%,
  100% {
    transform: translateY(0);
  }

  35% {
    transform: translateY(-14px);
  }

  55% {
    transform: translateY(-8px);
  }
}

@keyframes robot-success-pulse {
  0%,
  100% {
    opacity: 0.82;
    transform: scale(1);
  }

  50% {
    opacity: 1;
    transform: scale(1.24);
  }
}

@keyframes robot-success-voice {
  0%,
  100% {
    transform: scaleY(1);
    opacity: 0.92;
  }

  50% {
    transform: scaleY(0.78);
    opacity: 1;
  }
}

@keyframes robot-success-thruster {
  0%,
  100% {
    height: 26px;
    opacity: 0.62;
  }

  50% {
    height: 40px;
    opacity: 1;
  }
}

@keyframes robot-error-shake {
  0%,
  100% {
    transform: translate(0, 0);
  }

  20% {
    transform: translate(-4px, 2px);
  }

  40% {
    transform: translate(4px, -2px);
  }

  60% {
    transform: translate(-3px, 1px);
  }

  80% {
    transform: translate(3px, -1px);
  }
}

@keyframes robot-error-alert {
  0%,
  100% {
    opacity: 0;
  }

  50% {
    opacity: 1;
  }
}

@keyframes robot-error-voice {
  0%,
  100% {
    height: 4px;
  }

  50% {
    height: 11px;
  }
}

@keyframes robot-error-thruster {
  0%,
  100% {
    height: 16px;
    opacity: 0.2;
  }

  50% {
    height: 24px;
    opacity: 0.55;
  }
}

@media (prefers-reduced-motion: reduce) {
  .job-agent-robot--processing .job-agent-robot__frame,
  .job-agent-robot--processing .job-agent-robot__signal,
  .job-agent-robot--processing .job-agent-robot__scanner,
  .job-agent-robot--processing .job-agent-robot__voice-bar,
  .job-agent-robot--processing .job-agent-robot__thruster,
  .job-agent-robot--success .job-agent-robot__frame,
  .job-agent-robot--success .job-agent-robot__signal,
  .job-agent-robot--success .job-agent-robot__voice-bar,
  .job-agent-robot--success .job-agent-robot__thruster,
  .job-agent-robot--error .job-agent-robot__frame,
  .job-agent-robot--error .job-agent-robot__signal,
  .job-agent-robot--error .job-agent-robot__voice-bar,
  .job-agent-robot--error .job-agent-robot__thruster {
    animation: none;
  }
}
</style>
