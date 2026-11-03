import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';
import { Play, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { adminSettings } from '@/settings';

/**
 * "Video" in the top bar: a link to YouTube that opens the player in a dialog. Nothing loads from YouTube until the click,
 * and a click with a modifier key opens YouTube, as any link does.
 */
export function VideoButton() {
  const video = adminSettings().video;
  const [open, setOpen] = useState(false);
  const trigger = useRef<HTMLAnchorElement>(null);
  if (!video) return null;
  return (
    <>
      <Button variant="ghost" size="sm" asChild>
        <a
          ref={trigger}
          href={video.watch}
          target="_blank"
          rel="noopener"
          aria-haspopup="dialog"
          onClick={(e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
            e.preventDefault();
            setOpen(true);
          }}
        >
          <Play size={13} fill="currentColor" aria-hidden="true" />
          <span className="max-[480px]:sr-only">{__('Video', 'monoranks')}</span>
        </a>
      </Button>
      {open && <VideoDialog embed={video.embed} onClose={() => { setOpen(false); trigger.current?.focus(); }} />}
    </>
  );
}

/**
 * The player over everything, the WordPress admin bar included, so it lives on the body rather than in the app. Its colours
 * do not follow the theme. Keyboard focus goes round between the close button and the player: the two guards send it back.
 * Removing the player stops the video.
 */
function VideoDialog({ embed, onClose }: { embed: string; onClose: () => void }) {
  const close = useRef<HTMLButtonElement>(null);
  const player = useRef<HTMLIFrameElement>(null);
  useEffect(() => {
    close.current?.focus();
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = overflow;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const title = __('Video', 'monoranks');
  return createPortal(
    <div role="dialog" aria-modal="true" aria-label={title} className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/80 p-4" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <span tabIndex={0} onFocus={() => player.current?.focus()} />
      {/* The width follows the window's height too, so the player and the button over it always fit. */}
      <div className="relative aspect-video w-[min(960px,calc(100vw-32px),calc((100dvh-112px)*16/9))]">
        <button ref={close} type="button" aria-label={__('Close', 'monoranks')} title={__('Close', 'monoranks')} onClick={onClose} className="absolute -top-11 end-0 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border-0 bg-white/15 p-0 text-white hover:bg-white/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><X size={20} aria-hidden="true" /></button>
        <iframe ref={player} className="absolute inset-0 h-full w-full rounded-xl border-0 bg-black" src={embed} title={title} allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" />
      </div>
      <span tabIndex={0} onFocus={() => close.current?.focus()} />
    </div>,
    document.body,
  );
}
