import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

const SELECTOR = '.notice, .update-nag, .updated, .error, #message';

/**
 * WordPress prints its own notices (and other plugins') at the top of the content area, which on these screens means
 * above the MonoRanks band. They are not hidden — a core update warning matters — they are moved into the page, under
 * the title, where they read as part of it. WordPress does the same thing for the screens it draws itself.
 */
export function Notices({ className }: { className?: string }) {
  const slot = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const body = document.getElementById('wpbody-content');
    const here = slot.current;
    if (!body || !here) return;
    const move = () => {
      body.querySelectorAll<HTMLElement>(':scope > *').forEach((el) => {
        if (el.id === 'monoranks-admin' || !el.matches(SELECTOR)) return;
        here.appendChild(el);
      });
    };
    move();
    // A plugin that prints its notice after the page has loaded lands in the same place.
    const watch = new MutationObserver(move);
    watch.observe(body, { childList: true });
    return () => watch.disconnect();
  }, []);
  return <div ref={slot} className={cn('empty:hidden', className)} />;
}
