function toDate(input: string | number | Date | null | undefined): Date | null {
  if (!input) return null;
  const d = input instanceof Date ? input : new Date(input);
  return isNaN(d.getTime()) ? null : d;
}

function pad(n: number): string { return n < 10 ? `0${n}` : String(n); }

/** "2026-03-03" */
export function formatDate(input: string | number | Date | null | undefined, fallback = '\u2014'): string {
  const d = toDate(input);
  if (!d) return fallback;
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** "2026-03-03 19:28:19" */
export function formatDateTime(input: string | number | Date | null | undefined, fallback = '\u2014'): string {
  const d = toDate(input);
  if (!d) return fallback;
  return `${formatDate(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

/** "just now", "5m ago", falls back to "2026-03-03" after a week */
export function formatRelative(input: string | number | Date | null | undefined, fallback = '\u2014'): string {
  const d = toDate(input);
  if (!d) return fallback;
  const diffMs = Date.now() - d.getTime();
  const sec = Math.round(diffMs / 1000);
  if (sec < 60) return 'just now';
  const min = Math.round(sec / 60);
  if (min < 60) return `${min}m ago`;
  const hr = Math.round(min / 60);
  if (hr < 24) return `${hr}h ago`;
  const day = Math.round(hr / 24);
  if (day < 7) return `${day}d ago`;
  return formatDate(d);
}
