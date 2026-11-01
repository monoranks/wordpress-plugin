import * as React from 'react';
import { cn } from '@/lib/utils';

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
  return <span className={cn('inline-flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[12px] font-bold uppercase tracking-[.06em] text-mute', className)} {...props} />;
}

export { Card, CardHeader, CardTitle, CardBody, Tile, TileLabel };
