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
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <Button variant="ghost" size="sm" aria-label={__('Dismiss', 'monoranks')} title={__('Dismiss', 'monoranks')} onClick={onDismiss}><X size={15} aria-hidden="true" /></Button>
      </CardHeader>
      <CardBody>
        <div className="relative aspect-video w-full max-w-[640px] overflow-hidden rounded-xl bg-surface2">
          {playing ? (
            <iframe className="absolute inset-0 h-full w-full border-0" src={video.embed} title={title} allow="autoplay; encrypted-media; picture-in-picture" allowFullScreen referrerPolicy="strict-origin-when-cross-origin" />
          ) : (
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
              className="group absolute inset-0 flex items-center justify-center"
            >
              <img src={video.poster} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover" />
              <span className="relative flex h-16 w-16 items-center justify-center rounded-full bg-brand text-white shadow-pop transition-transform group-hover:scale-110 group-focus-visible:scale-110"><Play size={26} fill="currentColor" className="ml-1" aria-hidden="true" /></span>
            </a>
          )}
        </div>
      </CardBody>
    </Card>
  );
}
