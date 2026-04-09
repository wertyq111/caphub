export function formatDateTime(value) {
  if (!value) {
    return '--';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '--';
  }

  return new Intl.DateTimeFormat('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(date);
}

export function getStatusTagType(status) {
  switch (status) {
    case 'succeeded':
    case 'active':
      return 'success';
    case 'processing':
      return 'warning';
    case 'failed':
    case 'inactive':
      return 'danger';
    default:
      return 'info';
  }
}

export function startCase(value) {
  if (!value) {
    return '--';
  }

  return String(value)
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function toArray(value) {
  return Array.isArray(value) ? value : [];
}

export function buildSourcePreview(job) {
  if (!job) {
    return '--';
  }

  if (job.input_type === 'plain_text') {
    return job.source_text || '--';
  }

  const parts = [job.source_title, job.source_summary, job.source_body]
    .filter((item) => typeof item === 'string' && item.trim().length > 0);

  return parts.join(' / ') || '--';
}

export function buildTranslatedPreview(result) {
  if (!result?.translated_document_json) {
    return '--';
  }

  const payload = result.translated_document_json;
  if (typeof payload.text === 'string' && payload.text.trim().length > 0) {
    return payload.text;
  }

  const parts = [payload.title, payload.summary, payload.body]
    .filter((item) => typeof item === 'string' && item.trim().length > 0);

  return parts.join(' / ') || '--';
}

export function getInvocationCounts(summary = {}) {
  const normalizedSummary = summary ?? {};

  return {
    glossaryHits: normalizedSummary.glossary_hits_count ?? 0,
    riskFlags: normalizedSummary.risk_flags_count ?? 0,
    notes: normalizedSummary.notes_count ?? 0,
  };
}
