import * as React from 'react';
import { Slot } from 'radix-ui';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * shadcn's Button with the MonoRanks app's redesign (1.29): square, bold, no border. Default is a soft accent tint,
 * primary a solid accent fill with white text (never black), ghost text only.
 */
const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-none border-0 font-bold leading-none no-underline transition-[background-color,color,opacity] duration-100 disabled:pointer-events-none disabled:opacity-55 [&_svg]:pointer-events-none [&_svg]:shrink-0 cursor-pointer',
  {
    variants: {
      variant: {
        default: 'bg-tint text-brand-ink hover:bg-tint2 hover:text-brand-ink',
        primary: 'bg-brand text-white hover:opacity-90 hover:text-white',
        accent: 'bg-brand text-white hover:opacity-90 hover:text-white',
        ghost: 'bg-transparent text-ink2 hover:bg-tint hover:text-brand-ink',
        danger: 'bg-[color-mix(in_srgb,var(--critical)_10%,var(--surface))] text-critical hover:bg-critical hover:text-white',
        dangerSolid: 'bg-critical text-white hover:opacity-90 hover:text-white',
      },
      size: {
        default: 'h-10 px-[18px] text-[14px]',
        sm: 'h-8 px-3 text-[13px]',
        icon: 'h-8 w-8 px-0',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  },
);

/**
 * A button that is working (aria-busy) shows the app's small spinner in front of its label and ignores clicks; the label
 * stays, so the button keeps its width.
 */
function Button({ className, variant, size, asChild = false, children, ...props }: React.ComponentProps<'button'> & VariantProps<typeof buttonVariants> & { asChild?: boolean }) {
  const busy = props['aria-busy'] === true || props['aria-busy'] === 'true';
  const classes = cn(buttonVariants({ variant, size, className }), busy && 'pointer-events-none opacity-85');
  if (asChild) {
    // A link styled as a button: Slot needs exactly one child, so no spinner here.
    return <Slot.Root data-slot="button" className={classes} {...props}>{children}</Slot.Root>;
  }
  return (
    <button data-slot="button" className={classes} {...props}>
      {busy && <span className="spin" aria-hidden="true" />}
      {children}
    </button>
  );
}

export { Button, buttonVariants };
