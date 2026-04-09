import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useDemoSessionStore } from '../demoSession';

describe('useDemoSessionStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('records sync translations as the latest session activity', () => {
    const store = useDemoSessionStore();

    store.recordSyncTranslation(
      {
        input_type: 'plain_text',
        source_lang: 'zh',
        target_lang: 'en',
        content: {
          text: '乙烯价格上涨。',
        },
      },
      {
        translated_document: {
          text: 'Ethylene prices are rising.',
        },
        glossary_hits: [],
        risk_flags: [],
      },
    );

    expect(store.recentTasks).toHaveLength(1);
    expect(store.featuredTask.kind).toBe('sync');
    expect(store.featuredTask.status).toBe('succeeded');
    expect(store.featuredTask.sourcePreview).toContain('乙烯价格上涨');
    expect(store.featuredTask.translatedPreview).toContain('Ethylene prices are rising');
  });

  it('keeps async jobs in session history and enriches them with polling updates', () => {
    const store = useDemoSessionStore();

    store.recordAsyncJob(
      {
        input_type: 'plain_text',
        source_lang: 'zh',
        target_lang: 'en',
        content: {
          text: '乙烯价格上涨。',
        },
      },
      {
        job_id: 42,
        job_uuid: 'job-123',
        status: 'pending',
      },
    );

    store.updateAsyncJobStatus({
      job_uuid: 'job-123',
      status: 'processing',
      started_at: '2026-04-08T12:30:00Z',
    });

    store.attachAsyncResult('job-123', {
      job_id: 42,
      job_uuid: 'job-123',
      status: 'succeeded',
      translated_document: {
        text: 'Ethylene prices rose.',
      },
      glossary_hits: [
        {
          source_term: '乙烯',
          chosen_translation: 'ethylene',
        },
      ],
      risk_flags: ['Check numerical context.'],
      meta: {
        mode: 'async',
      },
    });

    expect(store.recentTasks).toHaveLength(1);
    expect(store.featuredTask.jobUuid).toBe('job-123');
    expect(store.featuredTask.status).toBe('succeeded');
    expect(store.featuredTask.glossaryCount).toBe(1);
    expect(store.featuredTask.riskCount).toBe(1);
    expect(store.latestResultTask.jobUuid).toBe('job-123');
  });
});
