import { describe, expect, it } from 'vitest';

import { getInvocationCounts } from '../adminPresentation';

describe('adminPresentation', () => {
  it('returns zero counts when invocation summary is null', () => {
    expect(getInvocationCounts(null)).toEqual({
      glossaryHits: 0,
      riskFlags: 0,
      notes: 0,
    });
  });
});
