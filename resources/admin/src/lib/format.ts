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

/** A big number in few characters: "30.9K", "۳۱ هزار". Under 10,000 it stays whole. */
export function fmtCompact(n: number): string {
  if (Math.abs(n) < 10000) return fmt(n);
  try {
    return new Intl.NumberFormat(adminSettings().locale, { notation: 'compact', maximumFractionDigits: 1 }).format(n);
  } catch {
    return fmt(n);
  }
}

/** A signed number for a delta: "+3", "−2". */
export function fmtSigned(n: number, unit = ''): string {
  return (n > 0 ? '+' : n < 0 ? '−' : '') + fmt(Math.abs(n)) + unit;
}
