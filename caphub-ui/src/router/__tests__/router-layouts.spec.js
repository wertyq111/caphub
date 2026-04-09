import { describe, expect, it } from 'vitest';
import { routes } from '../index';

describe('router layouts', () => {
  it('uses DemoLayout for demo pages and AdminLayout for admin pages', () => {
    const home = routes.find((route) => route.path === '/');
    const demo = routes.find((route) => route.path === '/demo');
    const login = routes.find((route) => route.path === '/admin/login');
    const admin = routes.find((route) => route.path === '/admin');

    expect(home.meta.layout).toBe('demo');
    expect(demo.meta.layout).toBe('demo');
    expect(login.meta.layout).toBe('admin-auth');
    expect(admin.meta.layout).toBe('admin');
  });
});
