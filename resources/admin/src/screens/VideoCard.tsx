import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Play, X } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import type { VideoData } from '@/lib/api';

/**
 * "Watch how it works": a poster that ships with the plugin and a play button. Nothing loads from YouTube until the click,
 * then the player takes the poster's place. A click with a modifier key opens YouTube, as any link does.
 */
export function VideoCard({ video, onDismiss }: { video: VideoData; onDismiss: () => void }) {
  const [playing, setPlaying] = useState(false);
  const title = __('Watch how it works', 'monoranks');
  const dismiss = <Button variant="ghost" size="sm" aria-label={__('Dismiss', 'monoranks')} title={__('Dismiss', 'monoranks')} onClick={onDismiss}><X size={15} /></Button>;
  if (playing) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
          {dismiss}
        </CardHeader>
        <CardBody>
          <div className="relative aspect-video w-full overflow-hidden rounded-xl bg-surface2">
            <iframe className="absolute inset-0 h-full w-full border-0" src={video.embed} title={title} allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" />
          </div>
        </CardBody>
      </Card>
    );
  }
  return (
    <Card>
      <div className="flex items-center gap-5 p-4 max-sm:flex-col max-sm:items-stretch">
        <a
          href={video.watch}
          target="_blank"
          rel="noopener"
          aria-label={__('Play video', 'monoranks')}
          onClick={(e) => {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
            e.preventDefault();
            setPlaying(true);
          }}
          className="group relative block aspect-video w-[200px] shrink-0 overflow-hidden rounded-lg bg-surface2 max-sm:w-full"
        >
          <img src={video.poster} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" />
          <span className="absolute bottom-2 start-2 flex h-8 w-8 items-center justify-center rounded-full bg-brand text-white transition-transform group-hover:scale-105"><Play size={14} fill="currentColor" /></span>
        </a>
        <div className="flex min-w-0 flex-1 items-center justify-between gap-3">
          <div className="min-w-0 text-[15px] font-semibold text-ink">{title}</div>
          <div className="flex shrink-0 items-center gap-2">
            <Button onClick={() => setPlaying(true)}><Play size={14} fill="currentColor" />{__('Watch', 'monoranks')}</Button>
            {dismiss}
          </div>
        </div>
      </div>
    </Card>
  );
}
