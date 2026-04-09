import { flushPromises, mount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { describe, expect, it, vi } from 'vitest';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import GlossaryPage from '../GlossaryPage.vue';

const { fetchGlossaries, createGlossary, updateGlossary, deleteGlossary, confirmMock, successMock } = vi.hoisted(() => ({
  fetchGlossaries: vi.fn(),
  createGlossary: vi.fn(),
  updateGlossary: vi.fn(),
  deleteGlossary: vi.fn(),
  confirmMock: vi.fn(),
  successMock: vi.fn(),
}));

vi.mock('../../../api/admin', () => ({
  fetchGlossaries,
  createGlossary,
  updateGlossary,
  deleteGlossary,
}));

vi.mock('element-plus', async () => {
  const actual = await vi.importActual('element-plus');

  return {
    ...actual,
    ElMessageBox: {
      confirm: confirmMock,
    },
    ElMessage: {
      success: successMock,
    },
  };
});

describe('GlossaryPage', () => {
  it('deletes a glossary entry after the list triggers delete', async () => {
    fetchGlossaries.mockResolvedValue({
      data: [
        {
          id: 1,
          term: 'ethylene',
          standard_translation: '乙烯',
          source_lang: 'en',
          target_lang: 'zh',
          domain: 'chemical_news',
          status: 'active',
          notes: null,
        },
      ],
    });
    deleteGlossary.mockResolvedValue(undefined);
    confirmMock.mockResolvedValue(undefined);

    const wrapper = mount(GlossaryPage, {
      global: {
        plugins: [
          createPinia(),
          [VueQueryPlugin, { queryClient: new QueryClient() }],
          ElementPlus,
        ],
        stubs: {
          AdminSectionCard: {
            template: '<section><slot name="actions" /><slot /></section>',
          },
          AdminStatCard: {
            template: '<div />',
          },
          GlossaryFormDialog: {
            template: '<div />',
          },
          GlossaryTable: {
            props: ['rows'],
            emits: ['edit', 'delete'],
            template: `<button data-test="delete-row" @click="$emit('delete', rows[0])">删除</button>`,
          },
        },
      },
    });

    await flushPromises();
    await wrapper.get('[data-test=\"delete-row\"]').trigger('click');
    await flushPromises();

    expect(confirmMock).toHaveBeenCalled();
    expect(deleteGlossary).toHaveBeenCalled();
    expect(deleteGlossary.mock.calls[0][0]).toBe(1);
    expect(successMock).toHaveBeenCalled();
  });
});
