import * as React from 'react';
import { cn } from '@/lib/utils';
import { fmt, fmtCompact } from '@/lib/format';
import { Tip } from '@/components/ui/tip';

/** The app's card after the 1.29 redesign: a borderless white block with a soft shadow and 16px corners. */
function Card({ className, ...props }: React.ComponentProps<'div'>) {
  return <div data-slot="card" className={cn('min-w-0 rounded-2xl bg-surface shadow-card', className)} {...props} />;
}

function CardHeader({ className, ...props }: React.ComponentProps<'div'>) {
  return <div data-slot="card-header" className={cn('flex flex-wrap items-center justify-between gap-3 px-7 pt-6 max-[782px]:px-[18px] max-[782px]:pt-[18px]', className)} {...props} />;
}

function CardTitle({ className, ...props }: React.ComponentProps<'h2'>) {
  return <h2 data-slot="card-title" className={cn('inline-flex items-center gap-2.5 text-[18px] font-bold tracking-[-0.01em] text-ink', className)} {...props} />;
}

function CardBody({ className, ...props }: React.ComponentProps<'div'>) {
  return <div data-slot="card-body" className={cn('px-7 pb-7 pt-5 max-[782px]:px-[18px] max-[782px]:pb-5', className)} {...props} />;
}

/** An inner tile: grey fill on the white card, 14px corners. */
function Tile({ className, ...props }: React.ComponentProps<'div'>) {
  return <div data-slot="tile" className={cn('flex min-w-0 flex-col gap-2.5 rounded-xl bg-surface2 px-5 py-[18px]', className)} {...props} />;
}

/** The short uppercase label over a big number. */
function TileLabel({ className, ...props }: React.ComponentProps<'span'>) {
  return <span className={cn('inline-flex min-w-0 items-center gap-1.5 text-[12px] font-bold uppercase tracking-[.06em] text-mute', className)} {...props} />;
}

/**
 * The big number of a tile. From 10,000 it is compact (30.9K) with the whole number in a tooltip, it wraps rather than
 * clips in a narrow tile, and a sign stays in front of the digits in a right-to-left admin.
 */
function TileNumber({ n, sign = '', className }: { n: number; sign?: '' | '+' | '−'; className?: string }) {
  const short = sign + fmtCompact(n);
  const whole = sign + fmt(n);
  const big = <span dir="ltr" className={cn('min-w-0 text-[32px] font-[750] leading-none tracking-[-0.03em] tabular-nums [overflow-wrap:anywhere]', className)}>{short}</span>;
  return short === whole ? big : <Tip text={whole} align="start" className="min-w-0 self-start">{big}</Tip>;
}

export { Card, CardHeader, CardTitle, CardBody, Tile, TileLabel, TileNumber };
