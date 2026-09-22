import * as React from 'react';
import { Slot } from 'radix-ui';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/** shadcn's Button with the MonoRanks app's variants: default (bordered), primary (ink), accent (blue), ghost. */
const buttonVariants = cva(
  'inline-flex items-center justify-center gap-[7px] whitespace-nowrap rounded-md border text-[13px] font-medium leading-none transition-[background-color,border-color,color,opacity] duration-100 disabled:pointer-events-none disabled:opacity-55 [&_svg]:pointer-events-none [&_svg]:shrink-0 cursor-pointer',
  {
    variants: {
      variant: {
        default: 'border-border bg-surface text-ink hover:bg-surface2 hover:border-line',
        primary: 'border-primary bg-primary text-primary-foreground hover:opacity-85',
        accent: 'border-brand bg-brand text-white hover:opacity-85',
        ghost: 'border-transparent bg-transparent text-ink2 hover:bg-surface2 hover:text-ink',
      },
      size: {
        default: 'h-[34px] px-3',
        sm: 'h-7 px-[10px] text-[12px]',
        icon: 'h-8 w-8 px-0',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  },
);

function Button({ className, variant, size, asChild = false, ...props }: React.ComponentProps<'button'> & VariantProps<typeof buttonVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot.Root : 'button';
  return <Comp data-slot="button" className={cn(buttonVariants({ variant, size, className }))} {...props} />;
}

export { Button, buttonVariants };
