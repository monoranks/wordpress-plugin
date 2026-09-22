import { adminSettings } from '@/settings';

/** Numbers in the admin's own script and separators: Persian and Arabic digits where the admin runs in those languages. */
export function fmt(n: number | null | undefined): string {
  if (n === null || n === undefined) return '—';
  try {
    return new Intl.NumberFormat(adminSettings().locale, { maximumFractionDigits: 0 }).format(n);
  } catch {
    return String(n);
  }
}

/** A signed number for a delta: "+3", "−2". */
export function fmtSigned(n: number, unit = ''): string {
  return (n > 0 ? '+' : n < 0 ? '−' : '') + fmt(Math.abs(n)) + unit;
}
