import { __, sprintf } from '@wordpress/i18n';
import { cn } from '@/lib/utils';
import type { Score } from '@/lib/api';
import { fmt, fmtSigned } from '@/lib/format';
import { adminSettings } from '@/settings';

/** good | warn | critical | none, the app's scoreColor thresholds (80 / 60). */
export function tone(score: Score) {
  if (score === null || score === undefined) return 'none';
  return score >= 80 ? 'good' : score >= 60 ? 'warn' : 'critical';
}

/** The plain word for a score, as the app's overview says it next to the big ring. */
export function scoreWord(score: Score) {
  if (score === null || score === undefined) return __('Not checked yet', 'monoranks');
  return score >= 80 ? __('Good', 'monoranks') : score >= 60 ? __('Needs work', 'monoranks') : __('Poor', 'monoranks');
}

const SIZES = { sm: [30, 3], md: [44, 4], lg: [64, 5], xl: [104, 8] } as const;

/** The app's ScoreBox: a ring with the number; no score is a dashed ring and a dash. */
export function Ring({ score, size = 'sm', label }: { score: Score; size?: keyof typeof SIZES; label?: string }) {
  const [px, sw] = SIZES[size];
  const r = (px - sw) / 2;
  const c = 2 * Math.PI * r;
  const mid = px / 2;
  const title = score === null ? __('Not checked yet', 'monoranks') : sprintf(__('%s of 100', 'monoranks'), fmt(score));
  return (
    <span className={cn('mr-score', `mr-${size}`, `mr-${tone(score)}`)} title={(label ? `${label}: ` : '') + title}>
      <span className="mr-ring">
        <svg width={px} height={px} viewBox={`0 0 ${px} ${px}`} aria-hidden="true">
          {score === null ? (
            <circle cx={mid} cy={mid} r={r} fill="none" stroke="currentColor" strokeWidth={sw} strokeDasharray="3 4" />
          ) : (
            <>
              <circle cx={mid} cy={mid} r={r} fill="none" stroke="var(--grayTrack)" strokeWidth={sw} />
              <circle cx={mid} cy={mid} r={r} fill="none" stroke="currentColor" strokeWidth={sw} strokeLinecap="round" strokeDasharray={`${(c * score) / 100} ${c}`} transform={`rotate(-90 ${mid} ${mid})`} />
            </>
          )}
        </svg>
        <span>{score === null ? '—' : fmt(score)}</span>
      </span>
      {label && <span className="mr-lbl">{label}</span>}
    </span>
  );
}

/** A short date such as "Oct 25" in the admin's language (and calendar). */
export function shortDate(iso: string | Date): string {
  const d = typeof iso === 'string' ? new Date(iso) : iso;
  if (Number.isNaN(d.getTime())) return '';
  try {
    return new Intl.DateTimeFormat(adminSettings().locale, { month: 'short', day: 'numeric' }).format(d);
  } catch {
    return d.toDateString();
  }
}

/**
 * A change in plain words: "+3 since Oct 25" for scores (the week before the last check), "+8% vs last 28 days" for
 * figures compared across 28-day periods. Green up, red down.
 */
export function Delta({ n, unit = '', since, period }: { n: number | null | undefined; unit?: string; since?: string; period?: 'previous28' }) {
  if (n === null || n === undefined) return null;
  const dir = n > 0 ? 'text-good-ink' : n < 0 ? 'text-critical' : 'text-mute';
  const txt = '⁨' + fmtSigned(n, unit) + '⁩';
  const label = period === 'previous28'
    /* translators: %s: signed change, for example "+8%". */
    ? sprintf(__('%s vs last 28 days', 'monoranks'), txt)
    : since
      /* translators: 1: signed change, for example "+3", 2: a short date, for example "Oct 25". */
      ? sprintf(__('%1$s since %2$s', 'monoranks'), txt, since)
      : txt;
  return <span className={cn('whitespace-nowrap text-[13px] font-semibold', dir)}>{label}</span>;
}

/** A 28-day sparkline: area, line, emphasised end point. */
export function Sparkline({ series }: { series: number[] }) {
  if (series.length < 2) return null;
  const max = Math.max(1, ...series);
  const pts = series.map((v, i) => [Math.round((200 * i) / (series.length - 1) * 10) / 10, Math.round((34 - (30 * v) / max) * 10) / 10]);
  const line = 'M' + pts.map((p) => p.join(' ')).join(' L');
  const last = pts[pts.length - 1];
  return (
    <svg className="block h-[34px] w-full" viewBox="0 0 200 38" preserveAspectRatio="none" aria-hidden="true">
      <path d={`${line} L200 38 L0 38 Z`} fill="var(--accentSoft)" />
      <path d={line} fill="none" stroke="var(--accent)" strokeWidth="1.5" />
      <circle cx={last[0]} cy={last[1]} r="2.5" fill="var(--accent)" />
    </svg>
  );
}
