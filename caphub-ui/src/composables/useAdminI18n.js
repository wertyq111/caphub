import { computed, ref } from 'vue';
import elementLocaleEn from 'element-plus/es/locale/lang/en';
import elementLocaleZhCn from 'element-plus/es/locale/lang/zh-cn';

const STORAGE_KEY = 'caphub_admin_locale';
const DEFAULT_LOCALE = 'zh-CN';

const messages = {
  'zh-CN': {
    brand: {
      name: 'CapHub Admin',
      tagline: '化工资讯翻译运营后台',
    },
    toolbar: {
      sectionLabel: '管理后台',
      subtitle: '统一管理术语、任务与 AI 调用状态。',
      welcome: '欢迎回来',
      signedInAs: '当前账号',
      signOut: '退出登录',
      openNavigation: '打开导航',
    },
    locale: {
      chinese: '中文',
      english: 'EN',
    },
    common: {
      actions: '操作',
      back: '返回',
      cancel: '取消',
      create: '新建',
      delete: '删除',
      detail: '详情',
      edit: '编辑',
      retry: '重试',
      save: '保存',
      unknown: '--',
      refresh: '刷新',
      totalRecords: '{count} 条记录',
    },
    states: {
      loadingTitle: '正在加载',
      loadingDescription: '后台数据正在同步，请稍候。',
      emptyTitle: '暂无数据',
      emptyDescription: '当前模块还没有可展示的记录。',
      errorTitle: '加载失败',
      errorDescription: '请稍后重试，或刷新页面后再次查看。',
    },
    navigation: {
      dashboard: '控制台',
      glossaries: '术语库',
      jobs: '翻译任务',
      invocations: '调用日志',
    },
    navigationDescriptions: {
      dashboard: '查看核心运行概览',
      glossaries: '维护标准术语与翻译规则',
      jobs: '跟踪翻译任务执行状态',
      invocations: '审查模型调用与耗时',
    },
    login: {
      badge: '后台管理入口',
      title: '欢迎回到 CapHub',
      subtitle: '进入统一控制台，管理术语、任务流转与 AI 调用记录。',
      formTitle: '管理员登录',
      formSubtitle: '使用后台账号进入管理空间。',
      email: '邮箱',
      password: '密码',
      submit: '登录',
      helper: '默认进入中文界面，可在此直接切换语言。',
      panelTitle: '化工资讯翻译运营中心',
      panelBody: '以更清晰的方式管理术语资产、监控任务处理链路，并复盘模型调用质量。',
      panelFeatureOne: '统一管理术语资产和翻译标准',
      panelFeatureTwo: '追踪任务状态与语言方向',
      panelFeatureThree: '回看 AI 调用明细与处理耗时',
      error: '登录失败，请检查账号或密码。',
    },
    pages: {
      dashboard: {
        title: '控制台',
        description: '查看术语、任务与 AI 调用的整体运行状态。',
      },
      glossaries: {
        title: '术语库管理',
        description: '维护标准术语、语言方向和优先级配置。',
      },
      jobs: {
        title: '翻译任务',
        description: '跟踪任务执行情况并进入详情页查看上下文。',
      },
      jobDetail: {
        title: '任务详情',
        description: '查看任务标识、语言设置与处理进度。',
      },
      invocations: {
        title: 'AI 调用日志',
        description: '审查模型请求、状态和耗时数据。',
      },
    },
    dashboard: {
      greeting: '欢迎回来',
      overview: '这里汇总后台当前最关键的运营指标。',
      quickAccessTitle: '快捷入口',
      quickAccessDescription: '进入核心管理模块继续处理后台工作。',
      glossaryCount: '术语条目',
      jobCount: '翻译任务',
      invocationCount: 'AI 调用',
    },
    translationProvider: {
      title: '翻译接口切换',
      description: '在后台统一切换当前翻译流量使用的接口提供方。',
      selectLabel: '当前接口',
      currentLabel: '生效中',
      saveAction: '应用切换',
      configured: '已配置',
      notConfigured: '未配置',
      helper: '切换后，新的同步与异步翻译任务都会走所选接口。',
      updateSuccess: '翻译接口已更新。',
      options: {
        openclaw: 'OpenClaw',
        hermes: 'Hermes',
      },
    },
    glossary: {
      newEntry: '新建术语',
      table: {
        id: 'ID',
        term: '术语',
        standardTranslation: '标准译法',
        languageDirection: '语言方向',
        rule: '规则信息',
        status: '状态',
      },
      form: {
        titleCreate: '新建术语',
        titleEdit: '编辑术语',
        term: '术语',
        standardTranslation: '标准译法',
        sourceLanguage: '源语言',
        targetLanguage: '目标语言',
        domain: '领域',
        priority: '优先级',
        status: '状态',
        notes: '备注',
      },
      listTitle: '术语列表',
      listSubtitle: '按术语、译法和规则状态集中管理当前可用条目。',
      deletePrompt: '确定删除术语“{term}”吗？',
      deleteSuccess: '术语已删除。',
    },
    jobs: {
      table: {
        id: 'ID',
        job: '任务',
        status: '状态',
        translationBody: '翻译正文',
        duration: '耗时',
        startedAt: '开始时间',
        finishedAt: '结束时间',
      },
      detail: {
        overview: '基础信息',
        configuration: '语言与任务设置',
        timing: '执行时间',
        content: '正文内容',
        jobUuid: '任务 UUID',
        status: '状态',
        sourceLanguage: '源语言',
        targetLanguage: '目标语言',
        mode: '模式',
        inputType: '输入类型',
        createdAt: '创建时间',
        updatedAt: '更新时间',
        startedAt: '开始时间',
        finishedAt: '结束时间',
        sourceBody: '翻译正文',
        translatedBody: '翻译后正文',
      },
      listTitle: '任务列表',
      listSubtitle: '聚焦查看任务状态、正文预览与处理进度。',
    },
    invocations: {
      table: {
        id: 'ID',
        agent: '代理',
        status: '状态',
        duration: '耗时',
        tokenUsage: 'Token 用量',
        createdAt: '创建时间',
      },
    },
    statuses: {
      active: '启用',
      inactive: '停用',
      processing: '进行中',
      succeeded: '成功',
      failed: '失败',
      pending: '等待中',
      queued: '排队中',
    },
  },
  'en-US': {
    brand: {
      name: 'CapHub Admin',
      tagline: 'Chemical Translation Operations Console',
    },
    toolbar: {
      sectionLabel: 'Admin Console',
      subtitle: 'Manage glossaries, jobs, and AI invocation health in one place.',
      welcome: 'Welcome back',
      signedInAs: 'Signed in as',
      signOut: 'Sign out',
      openNavigation: 'Open navigation',
    },
    locale: {
      chinese: '中文',
      english: 'EN',
    },
    common: {
      actions: 'Actions',
      back: 'Back',
      cancel: 'Cancel',
      create: 'Create',
      delete: 'Delete',
      detail: 'Detail',
      edit: 'Edit',
      retry: 'Retry',
      save: 'Save',
      unknown: '--',
      refresh: 'Refresh',
      totalRecords: '{count} records',
    },
    states: {
      loadingTitle: 'Loading',
      loadingDescription: 'Admin data is syncing. Please wait a moment.',
      emptyTitle: 'No data yet',
      emptyDescription: 'There are no records to display in this module right now.',
      errorTitle: 'Unable to load',
      errorDescription: 'Please try again shortly or refresh the page.',
    },
    navigation: {
      dashboard: 'Dashboard',
      glossaries: 'Glossaries',
      jobs: 'Jobs',
      invocations: 'Invocations',
    },
    navigationDescriptions: {
      dashboard: 'Review the key operating metrics',
      glossaries: 'Maintain standard terminology and translation rules',
      jobs: 'Track translation job progress',
      invocations: 'Inspect model calls and durations',
    },
    login: {
      badge: 'Admin Access',
      title: 'Welcome back to CapHub',
      subtitle: 'Enter the unified console to manage glossary assets, translation flows, and AI activity.',
      formTitle: 'Admin sign in',
      formSubtitle: 'Use your admin account to enter the workspace.',
      email: 'Email',
      password: 'Password',
      submit: 'Sign in',
      helper: 'Chinese is the default. You can switch languages here before signing in.',
      panelTitle: 'Chemical Translation Operations Center',
      panelBody: 'Manage terminology assets with more clarity, monitor job pipelines, and review model invocation quality from one control surface.',
      panelFeatureOne: 'Keep glossary assets and translation standards aligned',
      panelFeatureTwo: 'Track job states and language direction at a glance',
      panelFeatureThree: 'Review invocation detail and response duration',
      error: 'Sign in failed. Please check your credentials.',
    },
    pages: {
      dashboard: {
        title: 'Dashboard',
        description: 'Review the overall health of glossaries, jobs, and AI invocations.',
      },
      glossaries: {
        title: 'Glossary Management',
        description: 'Maintain standard terms, language direction, and priority settings.',
      },
      jobs: {
        title: 'Translation Jobs',
        description: 'Track execution progress and open job detail for more context.',
      },
      jobDetail: {
        title: 'Job Detail',
        description: 'Review identifiers, language settings, and processing status.',
      },
      invocations: {
        title: 'AI Invocations',
        description: 'Inspect model requests, statuses, and duration data.',
      },
    },
    dashboard: {
      greeting: 'Welcome back',
      overview: 'The most important operating signals are summarized here.',
      quickAccessTitle: 'Quick access',
      quickAccessDescription: 'Jump into the core management modules and continue your work.',
      glossaryCount: 'Glossary entries',
      jobCount: 'Translation jobs',
      invocationCount: 'AI invocations',
    },
    translationProvider: {
      title: 'Translation Interface',
      description: 'Switch the active translation provider for the admin console from one place.',
      selectLabel: 'Active interface',
      currentLabel: 'Live now',
      saveAction: 'Apply switch',
      configured: 'Configured',
      notConfigured: 'Not configured',
      helper: 'After switching, new sync and async translation requests will use the selected provider.',
      updateSuccess: 'The translation interface has been updated.',
      options: {
        openclaw: 'OpenClaw',
        hermes: 'Hermes',
      },
    },
    glossary: {
      newEntry: 'New glossary',
      table: {
        id: 'ID',
        term: 'Term',
        standardTranslation: 'Standard translation',
        languageDirection: 'Language direction',
        rule: 'Rule details',
        status: 'Status',
      },
      form: {
        titleCreate: 'New glossary entry',
        titleEdit: 'Edit glossary entry',
        term: 'Term',
        standardTranslation: 'Standard translation',
        sourceLanguage: 'Source language',
        targetLanguage: 'Target language',
        domain: 'Domain',
        priority: 'Priority',
        status: 'Status',
        notes: 'Notes',
      },
      listTitle: 'Glossary entries',
      listSubtitle: 'Manage terms, translations, and rule status from one table.',
      deletePrompt: 'Delete glossary entry "{term}"?',
      deleteSuccess: 'Glossary entry deleted.',
    },
    jobs: {
      table: {
        id: 'ID',
        job: 'Job',
        status: 'Status',
        translationBody: 'Source body',
        duration: 'Duration',
        startedAt: 'Started at',
        finishedAt: 'Finished at',
      },
      detail: {
        overview: 'Overview',
        configuration: 'Language and configuration',
        timing: 'Timing',
        content: 'Document content',
        jobUuid: 'Job UUID',
        status: 'Status',
        sourceLanguage: 'Source language',
        targetLanguage: 'Target language',
        mode: 'Mode',
        inputType: 'Input type',
        createdAt: 'Created at',
        updatedAt: 'Updated at',
        startedAt: 'Started at',
        finishedAt: 'Finished at',
        sourceBody: 'Source body',
        translatedBody: 'Translated body',
      },
      listTitle: 'Job list',
      listSubtitle: 'Review execution status, body preview, and processing progress at a glance.',
    },
    invocations: {
      table: {
        id: 'ID',
        agent: 'Agent',
        status: 'Status',
        duration: 'Duration',
        tokenUsage: 'Token usage',
        createdAt: 'Created at',
      },
    },
    statuses: {
      active: 'Active',
      inactive: 'Inactive',
      processing: 'Processing',
      succeeded: 'Succeeded',
      failed: 'Failed',
      pending: 'Pending',
      queued: 'Queued',
    },
  },
};

const localeState = ref(readStoredLocale());

export const adminLocaleOptions = [
  { value: 'zh-CN', labelKey: 'locale.chinese' },
  { value: 'en-US', labelKey: 'locale.english' },
];

function readStoredLocale() {
  const storage = getStorage();

  if (!storage) {
    return DEFAULT_LOCALE;
  }

  const storedValue = storage.getItem(STORAGE_KEY);
  return Object.hasOwn(messages, storedValue) ? storedValue : DEFAULT_LOCALE;
}

function resolveMessage(localeCode, key) {
  const segments = key.split('.');
  let cursor = messages[localeCode];

  for (const segment of segments) {
    if (!cursor || typeof cursor !== 'object' || !Object.hasOwn(cursor, segment)) {
      return undefined;
    }

    cursor = cursor[segment];
  }

  return cursor;
}

function persistLocale(localeCode) {
  const storage = getStorage();

  if (!storage) {
    return;
  }

  storage.setItem(STORAGE_KEY, localeCode);
}

function getStorage() {
  if (typeof globalThis === 'undefined') {
    return null;
  }

  const storageCandidate = globalThis.localStorage ?? globalThis.window?.localStorage;

  if (
    storageCandidate &&
    typeof storageCandidate.getItem === 'function' &&
    typeof storageCandidate.setItem === 'function'
  ) {
    return storageCandidate;
  }

  return null;
}

export function useAdminI18n() {
  const locale = computed(() => localeState.value);
  const isChinese = computed(() => localeState.value === DEFAULT_LOCALE);
  const elementLocale = computed(() =>
    localeState.value === 'en-US' ? elementLocaleEn : elementLocaleZhCn,
  );

  function setLocale(localeCode) {
    if (!Object.hasOwn(messages, localeCode)) {
      return;
    }

    localeState.value = localeCode;
    persistLocale(localeCode);
  }

  function toggleLocale() {
    setLocale(localeState.value === DEFAULT_LOCALE ? 'en-US' : DEFAULT_LOCALE);
  }

  function t(key, variables = {}) {
    const template =
      resolveMessage(localeState.value, key) ??
      resolveMessage(DEFAULT_LOCALE, key) ??
      key;

    if (typeof template !== 'string') {
      return key;
    }

    return template.replace(/\{(\w+)\}/g, (_, variableName) =>
      Object.hasOwn(variables, variableName) ? variables[variableName] : `{${variableName}}`,
    );
  }

  return {
    locale,
    isChinese,
    elementLocale,
    setLocale,
    t,
    toggleLocale,
  };
}
