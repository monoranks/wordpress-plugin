import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/** Small labelled items from the app's redesign: badge (24px, 3px corners, bold) and pill (26px, 4px corners). */
const badgeVariants = cva('inline-flex shrink-0 items-center whitespace-nowrap font-bold', {
  variants: {
    variant: {
      badge: 'h-6 gap-[5px] rounded-sm bg-surface2 px-2 text-[12px] text-ink2',
      warn: 'h-6 gap-[5px] rounded-sm bg-[color-mix(in_srgb,var(--warn)_15%,transparent)] px-2 text-[12px] text-[color-mix(in_srgb,var(--warn)_70%,var(--ink))]',
      pill: 'h-[26px] gap-[6px] rounded-md bg-surface2 ps-2 pe-2.5 text-[13px] text-ink',
    },
  },
  defaultVariants: { variant: 'badge' },
});

function Badge({ className, variant, ...props }: React.ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
  return <span data-slot="badge" className={cn(badgeVariants({ variant }), className)} {...props} />;
}

/** The 8px status dot inside a pill. */
function Dot({ tone = 'muted' }: { tone?: 'good' | 'warn' | 'critical' | 'muted' }) {
  const color = { good: 'bg-good', warn: 'bg-warn', critical: 'bg-critical', muted: 'bg-mute' }[tone];
  return <span className={cn('inline-block h-2 w-2 shrink-0 rounded-full', color)} />;
}

export type Severity = 'urgent' | 'high' | 'medium' | 'low';

/** A page's severity from its health score, the app's bands: under 40 urgent, under 60 high, under 80 medium. */
function severityOf(score: number | null | undefined): Severity {
  if (score === null || score === undefined) return 'low';
  return score < 40 ? 'urgent' : score < 60 ? 'high' : score < 80 ? 'medium' : 'low';
}

/**
 * Severity after a row's title as a small pill with its word, so a colour never has to be read alone: Urgent red, High
 * orange, Medium amber, Low grey, on a soft background of the same colour (the app's SeverityPill).
 */
function SeverityPill({ level }: { level: Severity }) {
  const color = { urgent: 'var(--critical)', high: 'var(--serious)', medium: 'var(--warn)', low: 'var(--muted)' }[level];
  const label = { urgent: __('Urgent', 'monoranks'), high: __('High', 'monoranks'), medium: __('Medium', 'monoranks'), low: __('Low', 'monoranks') }[level];
  return (
    <span className="inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2 py-px text-[12px] font-semibold leading-normal"
      style={{ color: level === 'low' ? 'var(--ink2)' : `color-mix(in srgb, ${color} 78%, var(--ink))`, background: `color-mix(in srgb, ${color} 15%, transparent)` }}>{label}</span>
  );
}

export { Badge, Dot, SeverityPill, severityOf, badgeVariants };
