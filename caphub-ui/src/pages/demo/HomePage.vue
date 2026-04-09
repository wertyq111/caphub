<script setup>
import { RouterLink } from 'vue-router';

const navItems = [
  { label: '主页', to: '/', active: true },
  { label: '翻译中心', to: '/demo/translate', active: false },
  { label: '代理监控', to: null, active: false },
  { label: '数据分析', to: null, active: false },
  { label: '设置', to: null, active: false },
];

const agentNodes = [
  {
    name: 'Agent Alpha',
    ping: '12ms',
    load: '15%',
    accent: 'cyan',
    image: '◉',
  },
  {
    name: 'Agent Beta',
    ping: '18ms',
    load: '22%',
    accent: 'fuchsia',
    image: '◎',
  },
  {
    name: 'Agent Gamma',
    ping: '15ms',
    load: '18%',
    accent: 'sky',
    image: '◌',
  },
];

const systemModules = [
  { title: '翻译模块', status: '活跃', load: '15%', position: 'left-top', icon: '文' },
  { title: '任务监控', status: '活跃', load: '15%', position: 'right-top', icon: '任' },
  { title: '术语表', status: '活跃', load: '15%', position: 'left-bottom', icon: '术' },
  { title: '知识库', status: '活跃', load: '15%', position: 'right-bottom', icon: '知' },
  { title: '工作流', status: '活跃', load: '15%', position: 'center-bottom', icon: '流' },
];

const pulses = [
  '10:45:32 · API 请求成功 · 翻译服务',
  '10:45:30 · 系统核心同步完成',
  '10:45:28 · Agent Alpha 负载更新',
  'API 延迟: 18ms (平均)',
  '错误日志: 0',
];

const chartBars = [44, 58, 61, 53, 71, 66, 80];
</script>

<template>
  <div class="space-y-5">
    <section class="relative overflow-hidden rounded-[1.9rem] border border-cyan-300/15 bg-[linear-gradient(180deg,#07111f_0%,#07101c_30%,#040b14_100%)] shadow-[0_35px_120px_rgba(3,8,18,0.65)]">
      <div class="pointer-events-none absolute inset-0">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_18%,rgba(34,211,238,0.12),transparent_26%),radial-gradient(circle_at_78%_22%,rgba(168,85,247,0.08),transparent_18%)]" />
        <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(64,196,255,0.08)_1px,transparent_1px),linear-gradient(90deg,rgba(64,196,255,0.08)_1px,transparent_1px)] [background-size:36px_36px]" />
      </div>

      <header class="relative border-b border-cyan-300/12 bg-[linear-gradient(180deg,rgba(9,20,34,0.98),rgba(7,15,27,0.96))] px-4 py-3 sm:px-5">
        <div class="flex flex-wrap items-center gap-3 lg:flex-nowrap">
          <div class="flex min-w-[12rem] items-center gap-3 rounded-2xl border border-cyan-300/15 bg-cyan-400/6 px-4 py-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[radial-gradient(circle,rgba(103,232,249,0.45),rgba(6,24,42,0.85))] text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.35)]">
              ✦
            </div>
            <div>
              <div class="text-[11px] uppercase tracking-[0.3em] text-cyan-100/55">Matrix</div>
              <div class="text-xl font-semibold tracking-tight text-cyan-50">AI 控制矩阵</div>
            </div>
          </div>

          <nav class="flex flex-1 flex-wrap items-center gap-2 rounded-2xl border border-white/8 bg-white/[0.03] px-2 py-2">
            <component
              :is="item.to ? RouterLink : 'span'"
              v-for="item in navItems"
              :key="item.label"
              :to="item.to || undefined"
              class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition"
              :class="item.active
                ? 'bg-cyan-300/14 text-cyan-50 shadow-[inset_0_0_0_1px_rgba(103,232,249,0.18),0_0_22px_rgba(34,211,238,0.12)]'
                : 'text-slate-400 hover:bg-white/[0.04] hover:text-slate-200'"
            >
              {{ item.label }}
            </component>
          </nav>

          <div class="flex items-center gap-3 lg:ml-auto">
            <div class="flex min-w-[14rem] items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-4 py-2 text-sm text-slate-400">
              <span>⌕</span>
              <span>搜索</span>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-slate-300">◔</div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-white/[0.04] text-slate-300">◉</div>
          </div>
        </div>
      </header>

      <div class="relative grid gap-4 p-4 lg:grid-cols-[16rem_minmax(0,1fr)_16rem] lg:p-5">
        <aside class="rounded-[1.55rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(8,20,36,0.96),rgba(6,14,25,0.96))] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
          <div class="mb-3 flex items-center justify-between">
            <div>
              <div class="text-[11px] uppercase tracking-[0.28em] text-cyan-100/50">AI 代理中枢</div>
              <div class="mt-1 text-2xl font-semibold text-cyan-50">Agent Hub</div>
            </div>
            <div class="text-slate-500">•••</div>
          </div>

          <div class="space-y-3">
            <article
              v-for="agent in agentNodes"
              :key="agent.name"
              class="rounded-[1.35rem] border p-3"
              :class="agent.accent === 'fuchsia'
                ? 'border-fuchsia-300/20 bg-[linear-gradient(180deg,rgba(38,16,54,0.92),rgba(18,18,34,0.92))]'
                : 'border-cyan-300/18 bg-[linear-gradient(180deg,rgba(10,28,49,0.92),rgba(8,17,31,0.95))]'"
            >
              <div class="flex gap-3">
                <div
                  class="flex h-24 w-24 shrink-0 items-center justify-center rounded-[1.1rem] border text-4xl shadow-[0_0_28px_rgba(34,211,238,0.2)]"
                  :class="agent.accent === 'fuchsia'
                    ? 'border-fuchsia-300/30 bg-[radial-gradient(circle,rgba(232,121,249,0.35),rgba(31,20,49,0.95))] text-fuchsia-200'
                    : 'border-cyan-300/30 bg-[radial-gradient(circle,rgba(34,211,238,0.32),rgba(10,25,44,0.95))] text-cyan-100'"
                >
                  {{ agent.image }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-2">
                    <div class="text-lg font-semibold" :class="agent.accent === 'fuchsia' ? 'text-fuchsia-200' : 'text-cyan-100'">
                      {{ agent.name }}
                    </div>
                    <span class="text-sm" :class="agent.accent === 'fuchsia' ? 'text-fuchsia-300' : 'text-cyan-300'">ϟ</span>
                  </div>
                  <div class="mt-2 space-y-1.5 text-sm text-slate-300">
                    <div>Ping: {{ agent.ping }}</div>
                    <div>Load: {{ agent.load }}</div>
                  </div>
                  <div class="mt-3 h-2 rounded-full bg-slate-950/70">
                    <div
                      class="h-full rounded-full"
                      :class="agent.accent === 'fuchsia'
                        ? 'w-[52%] bg-[linear-gradient(90deg,#f472b6,#d946ef)]'
                        : 'w-[44%] bg-[linear-gradient(90deg,#67e8f9,#22d3ee)]'"
                    />
                  </div>
                </div>
              </div>

              <div class="mt-3 flex items-center gap-2">
                <span
                  v-for="index in 8"
                  :key="`${agent.name}-${index}`"
                  class="h-3 w-1.5 rounded-full"
                  :class="agent.accent === 'fuchsia' ? 'bg-fuchsia-300/85' : 'bg-cyan-300/85'"
                />
              </div>

              <div class="mt-3 grid grid-cols-2 gap-2">
                <button class="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2 text-sm text-slate-200 transition hover:bg-white/[0.08]">控制</button>
                <button class="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2 text-sm text-slate-200 transition hover:bg-white/[0.08]">日志</button>
              </div>
            </article>
          </div>
        </aside>

        <main class="space-y-4">
          <section class="rounded-[1.55rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(7,19,34,0.96),rgba(5,13,23,0.98))] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
            <div class="rounded-[1.4rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(10,22,40,0.86),rgba(6,14,25,0.92))] p-4">
              <div class="mb-4 flex items-center justify-center">
                <div class="rounded-full border border-cyan-300/20 bg-cyan-300/8 px-6 py-2 text-2xl font-semibold text-cyan-50 shadow-[0_0_24px_rgba(34,211,238,0.15)]">
                  系统核心
                </div>
              </div>

              <div class="relative h-[22rem] overflow-hidden rounded-[1.35rem] border border-cyan-300/14 bg-[radial-gradient(circle_at_50%_45%,rgba(44,193,255,0.14),transparent_24%),linear-gradient(180deg,rgba(4,16,32,0.82),rgba(3,10,20,0.96))]">
                <div class="absolute inset-0 opacity-35 [background-image:linear-gradient(rgba(103,232,249,0.09)_1px,transparent_1px),linear-gradient(90deg,rgba(103,232,249,0.09)_1px,transparent_1px)] [background-size:28px_28px]" />

                <div class="absolute left-1/2 top-[46%] h-40 w-40 -translate-x-1/2 -translate-y-1/2 rounded-full border border-cyan-300/25 bg-[radial-gradient(circle,rgba(96,165,250,0.22),rgba(9,23,41,0.1)_56%,transparent_62%)] shadow-[0_0_60px_rgba(56,189,248,0.24)]" />
                <div class="absolute left-1/2 top-[46%] h-24 w-24 -translate-x-1/2 -translate-y-1/2 rounded-full border border-fuchsia-300/30 bg-[radial-gradient(circle,rgba(232,121,249,0.35),rgba(15,22,45,0.9))] shadow-[0_0_40px_rgba(217,70,239,0.28)]" />
                <div class="absolute left-1/2 top-[46%] h-6 w-6 -translate-x-1/2 -translate-y-1/2 rounded-full bg-cyan-200 shadow-[0_0_24px_rgba(165,243,252,0.8)]" />

                <div class="absolute left-[22%] top-[26%] h-px w-[23%] bg-[linear-gradient(90deg,rgba(34,211,238,0.55),rgba(34,211,238,0.1))]" />
                <div class="absolute right-[22%] top-[26%] h-px w-[23%] bg-[linear-gradient(90deg,rgba(34,211,238,0.1),rgba(34,211,238,0.55))]" />
                <div class="absolute left-[22%] bottom-[30%] h-px w-[23%] bg-[linear-gradient(90deg,rgba(34,211,238,0.55),rgba(34,211,238,0.1))]" />
                <div class="absolute right-[22%] bottom-[30%] h-px w-[23%] bg-[linear-gradient(90deg,rgba(34,211,238,0.1),rgba(34,211,238,0.55))]" />
                <div class="absolute left-1/2 bottom-[18%] h-[18%] w-px -translate-x-1/2 bg-[linear-gradient(180deg,rgba(34,211,238,0.4),rgba(34,211,238,0.08))]" />

                <article
                  v-for="module in systemModules"
                  :key="module.title"
                  class="absolute w-[12rem] rounded-[1.2rem] border border-cyan-300/16 bg-[linear-gradient(180deg,rgba(14,33,55,0.9),rgba(8,18,31,0.96))] p-3 shadow-[0_0_24px_rgba(34,211,238,0.12)]"
                  :class="{
                    'left-6 top-8': module.position === 'left-top',
                    'right-6 top-8': module.position === 'right-top',
                    'left-10 bottom-8': module.position === 'left-bottom',
                    'right-10 bottom-8': module.position === 'right-bottom',
                    'left-1/2 bottom-6 -translate-x-1/2': module.position === 'center-bottom',
                  }"
                >
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-300/18 bg-cyan-300/10 text-cyan-100">
                      {{ module.icon }}
                    </div>
                    <div class="text-xl font-semibold text-cyan-50">{{ module.title }}</div>
                  </div>
                  <div class="mt-3 space-y-1.5 text-sm text-slate-200">
                    <div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(52,211,153,0.7)]" />状态: {{ module.status }}</div>
                    <div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(52,211,153,0.7)]" />负载: {{ module.load }}</div>
                  </div>
                </article>
              </div>
            </div>
          </section>

          <section class="rounded-[1.55rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(7,19,34,0.96),rgba(5,13,23,0.98))] p-4">
            <div class="mb-4 flex items-center justify-between">
              <div>
                <div class="text-[11px] uppercase tracking-[0.28em] text-cyan-100/55">神经连接</div>
                <div class="mt-1 text-2xl font-semibold text-cyan-50">Neural Link</div>
              </div>
              <div class="text-slate-500">•••</div>
            </div>

            <div class="rounded-[1.25rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(10,24,43,0.92),rgba(7,15,26,0.96))] p-4">
              <div class="flex gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[radial-gradient(circle,rgba(103,232,249,0.35),rgba(8,26,44,0.94))] text-cyan-100">
                  ◎
                </div>
                <div>
                  <div class="text-lg font-semibold text-cyan-100">AI 助手</div>
                  <p class="mt-1 text-sm leading-7 text-slate-300">
                    系统核心运行稳定。最新的翻译任务已完成，耗时 1.5 秒。
                  </p>
                </div>
              </div>
            </div>

            <div class="mt-4 flex items-center gap-3 rounded-[1.1rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(10,24,43,0.86),rgba(6,13,24,0.96))] px-4 py-3 shadow-[inset_0_0_24px_rgba(34,211,238,0.08)]">
              <input
                readonly
                value="输入指令与 AI 互动..."
                class="flex-1 bg-transparent text-sm text-slate-400 outline-none"
              />
              <RouterLink
                to="/demo/translate"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-cyan-300 text-lg font-semibold text-slate-950 transition hover:bg-cyan-200"
              >
                ➤
              </RouterLink>
            </div>
          </section>
        </main>

        <aside class="rounded-[1.55rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(8,20,36,0.96),rgba(6,14,25,0.96))] p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]">
          <div class="mb-4 flex items-center justify-between">
            <div>
              <div class="text-[11px] uppercase tracking-[0.28em] text-cyan-100/55">系统脉冲</div>
              <div class="mt-1 text-2xl font-semibold text-cyan-50">Pulse</div>
            </div>
            <div class="text-slate-500">•••</div>
          </div>

          <div class="space-y-3">
            <div class="rounded-[1.2rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(9,22,39,0.92),rgba(7,15,28,0.96))] p-4">
              <ul class="space-y-2.5 text-sm text-slate-300">
                <li
                  v-for="item in pulses"
                  :key="item"
                  class="flex gap-2"
                >
                  <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-emerald-300 shadow-[0_0_12px_rgba(52,211,153,0.65)]" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>

            <div class="rounded-[1.2rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(9,22,39,0.92),rgba(7,15,28,0.96))] p-4">
              <div class="mb-4 flex items-center justify-between">
                <div class="text-sm text-slate-300">CPU</div>
                <div class="text-xs text-cyan-100/60">CPU • 内存</div>
              </div>
              <div class="flex h-28 items-end gap-2">
                <div
                  v-for="(bar, index) in chartBars"
                  :key="index"
                  class="relative flex-1 rounded-t-full bg-[linear-gradient(180deg,rgba(74,222,128,0.92),rgba(34,197,94,0.15))]"
                  :style="{ height: `${bar}%` }"
                >
                  <div class="absolute inset-x-0 top-0 h-2 rounded-full bg-emerald-200/85 blur-[2px]" />
                </div>
              </div>
              <div class="mt-3 flex justify-between text-xs text-slate-500">
                <span>0%</span>
                <span>100%</span>
              </div>
            </div>

            <div class="relative overflow-hidden rounded-[1.2rem] border border-cyan-300/14 bg-[linear-gradient(180deg,rgba(9,22,39,0.92),rgba(7,15,28,0.96))] p-4">
              <div class="absolute inset-0 opacity-30">
                <div class="h-full w-full [background-image:repeating-linear-gradient(180deg,rgba(45,212,191,0.4)_0_2px,transparent_2px_8px)] [mask-image:linear-gradient(180deg,transparent,black_10%,black_90%,transparent)]" />
              </div>
              <div class="relative text-[11px] uppercase tracking-[0.28em] text-cyan-100/55">Data Stream</div>
              <div class="relative mt-4 space-y-2 font-mono text-[10px] leading-4 text-cyan-300/40">
                <div v-for="index in 18" :key="index">
                  01001100111001010101001100101101010111001010
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </div>
</template>
