export function formatDateTime(value, localeCode = 'zh-CN') {
  if (!value) {
    return '--';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '--';
  }

  return new Intl.DateTimeFormat(localeCode, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(date);
}

export function truncateText(value, maxLength = 140) {
  const normalized = normalizeRichText(value);

  if (normalized === '--' || normalized.length <= maxLength) {
    return normalized;
  }

  return `${normalized.slice(0, maxLength).trimEnd()}...`;
}

export function getStatusTagType(status) {
  switch (status) {
    case 'succeeded':
    case 'active':
      return 'success';
    case 'processing':
    case 'pending':
    case 'queued':
      return 'warning';
    case 'failed':
    case 'inactive':
      return 'danger';
    default:
      return 'info';
  }
}

export function getStatusLabel(status, translate) {
  if (!status) {
    return '--';
  }

  if (typeof translate !== 'function') {
    return startCase(status);
  }

  const translated = translate(`statuses.${status}`);
  return translated === `statuses.${status}` ? startCase(status) : translated;
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
  return truncateText(buildSourceDocument(job), 160);
}

export function buildTranslatedPreview(result) {
  return truncateText(buildTranslatedDocument(result), 160);
}

export function getInvocationCounts(summary = {}) {
  const normalizedSummary = summary ?? {};

  return {
    glossaryHits: normalizedSummary.glossary_hits_count ?? 0,
    riskFlags: normalizedSummary.risk_flags_count ?? 0,
    notes: normalizedSummary.notes_count ?? 0,
  };
}

export function formatDuration(startedAt, finishedAt) {
  if (!startedAt || !finishedAt) {
    return '--';
  }

  const start = new Date(startedAt);
  const end = new Date(finishedAt);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return '--';
  }

  const ms = end - start;
  if (ms < 0) {
    return '--';
  }
  if (ms < 1000) {
    return `${ms}ms`;
  }

  return `${(ms / 1000).toFixed(1)}s`;
}

export function formatDurationMs(durationMs) {
  if (typeof durationMs !== 'number' || Number.isNaN(durationMs) || durationMs < 0) {
    return '--';
  }

  if (durationMs < 1000) {
    return `${Math.round(durationMs)}ms`;
  }

  return `${(durationMs / 1000).toFixed(1)}s`;
}

export function resolveRequestError(error, fallback = 'Something went wrong.') {
  return error?.response?.data?.message ?? fallback;
}

export function buildSourceDocument(job) {
  if (!job) {
    return '--';
  }

  const candidates = [job.source_text, job.source_body, job.source_summary, job.source_title];
  return firstNonEmptyDocument(candidates);
}

export function buildTranslatedDocument(result) {
  const payload = result?.translated_document_json ?? result?.result?.translated_document_json;

  if (!payload) {
    return '--';
  }

  const candidates = [payload.text, payload.body, payload.summary, payload.title];
  return firstNonEmptyDocument(candidates);
}

export function normalizeRichText(value) {
  if (typeof value !== 'string' || value.trim().length === 0) {
    return '--';
  }

  return value
    .replace(/<\s*br\s*\/?>/gi, '\n')
    .replace(/<\/(p|div|section|article|li|h[1-6])>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/\r\n/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .replace(/[ \t]{2,}/g, ' ')
    .trim() || '--';
}

function firstNonEmptyDocument(values) {
  for (const value of values) {
    const normalized = normalizeRichText(value);
    if (normalized !== '--') {
      return normalized;
    }
  }

  return '--';
}
