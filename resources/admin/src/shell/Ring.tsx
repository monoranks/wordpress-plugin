import { __, sprintf } from '@wordpress/i18n';
import { cn } from '@/lib/utils';
import type { Score } from '@/lib/api';
import { fmt, fmtSigned } from '@/lib/format';

/** good | warn | critical | none, the app's scoreColor thresholds (80 / 60). */
export function tone(score: Score) {
  if (score === null || score === undefined) return 'none';
  return score >= 80 ? 'good' : score >= 60 ? 'warn' : 'critical';
}

/** The app's ScoreBox: a ring with the number, 30px in tables and 64px on tiles; no score is a dashed ring and a dash. */
export function Ring({ score, size = 'sm', label }: { score: Score; size?: 'sm' | 'lg'; label?: string }) {
  const px = size === 'lg' ? 64 : 30;
  const sw = size === 'lg' ? 5 : 3;
  const r = (px - sw) / 2;
  const c = 2 * Math.PI * r;
  const mid = px / 2;
  const title = score === null ? __('Not scored yet', 'monoranks') : sprintf(__('%s of 100', 'monoranks'), fmt(score));
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

/** "+3 this week" / "−2 this week". */
export function Delta({ n, unit = '' }: { n: number | null | undefined; unit?: string }) {
  if (n === null || n === undefined) return null;
  const dir = n > 0 ? 'text-good-ink' : n < 0 ? 'text-critical' : 'text-mute';
  const txt = fmtSigned(n, unit);
  return <span className={cn('text-[12px] font-medium', dir)}>{sprintf(__('%s this week', 'monoranks'), '⁨' + txt + '⁩')}</span>;
}

/** A 28-day sparkline: area, line, emphasised end point. */
export function Sparkline({ series }: { series: number[] }) {
  if (series.length < 2) return null;
  const max = Math.max(1, ...series);
  const pts = series.map((v, i) => [Math.round((200 * i) / (series.length - 1) * 10) / 10, Math.round((34 - (30 * v) / max) * 10) / 10]);
  const line = 'M' + pts.map((p) => p.join(' ')).join(' L');
  const last = pts[pts.length - 1];
  return (
    <svg className="block h-[38px] w-full" viewBox="0 0 200 38" preserveAspectRatio="none" aria-hidden="true">
      <path d={`${line} L200 38 L0 38 Z`} fill="var(--accentSoft)" />
      <path d={line} fill="none" stroke="var(--accent)" strokeWidth="1.5" />
      <circle cx={last[0]} cy={last[1]} r="2.5" fill="var(--accent)" />
    </svg>
  );
}
