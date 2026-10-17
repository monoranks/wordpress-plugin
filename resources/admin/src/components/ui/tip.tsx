import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { Info } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * A short explanation behind a hover or keyboard focus, so rows can stay one short line. Without children it shows a small
 * (i) button; with one child (a chip, a time, a button) that child is the trigger. Escape closes it.
 */
export function Tip({ text, children, className, align = 'center' }: { text: React.ReactNode; children?: React.ReactElement<Record<string, unknown>>; className?: string; align?: 'center' | 'start' | 'end' }) {
  const id = React.useId();
  const [open, setOpen] = React.useState(false);
  const show = () => setOpen(true);
  const hide = () => setOpen(false);
  const trigger = children
    ? React.cloneElement(children, { 'aria-describedby': id, tabIndex: (children.props.tabIndex as number | undefined) ?? 0 })
    : (
      <button type="button" aria-describedby={id} aria-label={__('More info', 'monoranks')} className="inline-flex h-4 w-4 items-center justify-center rounded-full text-mute hover:text-ink focus-visible:text-ink focus-visible:outline-2 focus-visible:outline-brand">
        <Info size={13} aria-hidden="true" />
      </button>
    );
  return (
    <span className={cn('relative inline-flex items-center', className)} onMouseEnter={show} onMouseLeave={hide} onFocus={show} onBlur={hide} onKeyDown={(e) => { if (e.key === 'Escape') hide(); }}>
      {trigger}
      <span id={id} role="tooltip" className={cn('pointer-events-none absolute bottom-[calc(100%+6px)] z-50 w-max max-w-[260px] rounded-md bg-ink px-2.5 py-1.5 text-start text-[12px] font-normal leading-snug text-page shadow-md', { center: 'left-1/2 -translate-x-1/2', start: 'start-0', end: 'end-0' }[align], open ? 'visible opacity-100' : 'invisible opacity-0')}>{text}</span>
    </span>
  );
}
