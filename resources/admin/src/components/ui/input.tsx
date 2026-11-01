import * as React from 'react';
import { cn } from '@/lib/utils';

/** shadcn's Input at the MonoRanks app's 40px height. */
function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        'flex h-[42px] w-full max-w-[440px] rounded-md border border-input bg-surface px-[14px] text-[14px] text-ink outline-none placeholder:text-mute focus:border-brand focus:shadow-[0_0_0_3px_var(--tint)] disabled:opacity-55',
        className,
      )}
      {...props}
    />
  );
}

export { Input };
