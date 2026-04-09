import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

function createStorageMock() {
  const storage = new Map();

  return {
    clear: () => storage.clear(),
    getItem: (key) => (storage.has(key) ? storage.get(key) : null),
    removeItem: (key) => storage.delete(key),
    setItem: (key, value) => storage.set(key, String(value)),
  };
}

describe('useAdminI18n', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.stubGlobal('localStorage', createStorageMock());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('defaults to Chinese when there is no stored locale', async () => {
    const { useAdminI18n } = await import('../useAdminI18n');
    const { locale, t } = useAdminI18n();

    expect(locale.value).toBe('zh-CN');
    expect(t('navigation.dashboard')).toBe('控制台');
  });

  it('persists the selected admin locale', async () => {
    const { useAdminI18n } = await import('../useAdminI18n');
    const { locale, setLocale, t } = useAdminI18n();

    setLocale('en-US');

    expect(locale.value).toBe('en-US');
    expect(globalThis.localStorage.getItem('caphub_admin_locale')).toBe('en-US');
    expect(t('navigation.jobs')).toBe('Jobs');
  });
});
