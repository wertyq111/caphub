import { describe, expect, it } from 'vitest';

import { formatDuration, formatDurationMs, getInvocationCounts } from '../adminPresentation';

describe('adminPresentation', () => {
  it('returns zero counts when invocation summary is null', () => {
    expect(getInvocationCounts(null)).toEqual({
      glossaryHits: 0,
      riskFlags: 0,
      notes: 0,
    });
  });

  it('formats durations for admin timing chips', () => {
    expect(
      formatDuration('2026-04-14T10:00:00.000Z', '2026-04-14T10:00:12.340Z'),
    ).toBe('12.3s');
    expect(
      formatDuration('2026-04-14T10:00:00.000Z', '2026-04-14T10:00:00.250Z'),
    ).toBe('250ms');
    expect(formatDuration(null, '2026-04-14T10:00:00.250Z')).toBe('--');
  });

  it('formats invocation durations from milliseconds', () => {
    expect(formatDurationMs(250)).toBe('250ms');
    expect(formatDurationMs(2400)).toBe('2.4s');
    expect(formatDurationMs(-1)).toBe('--');
  });
});
