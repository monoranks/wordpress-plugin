import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { ArrowUpRight, ArrowRight, CheckCircle2, AlertCircle, AlertTriangle } from 'lucide-react';
import { adminSettings, type Notice } from '@/settings';
import { Notices } from '@/shell/Notices';
import { cn } from '@/lib/utils';
import { out } from '@/lib/links';
import type { Screen } from '@/lib/screen';

/** The plugin's own SVG (assets/*.svg), inlined so currentColor applies. Static files shipped with the plugin. */
export function Logo({ which, className }: { which: 'wordmark' | 'mark' | 'veronalabs'; className?: string }) {
  return <span className={cn('inline-flex [&>svg]:block', className)} dangerouslySetInnerHTML={{ __html: adminSettings().logos[which] }} />;
}

const measure = 'mx-auto w-full max-w-[1260px] px-10 max-[960px]:px-6 max-[782px]:px-4';

/**
 * The frame: a dark brand band (logo, section links, help, a way into MonoRanks), the light title area, the work area,
 * and the service footer with the publisher credit.
 */
export function Shell({ section, go, title, description, actions, children }: { section: Screen; go: (next: Screen) => void; title: string; description?: ReactNode; actions?: ReactNode; children: ReactNode }) {
  const s = adminSettings();
  const sections: { id: Screen; label: string; href: string }[] = [
    { id: 'overview', label: __('Overview', 'monoranks'), href: s.urls.overview },
    { id: 'settings', label: __('Settings', 'monoranks'), href: s.urls.settings },
  ];
  return (
    <>
      <header className="bg-band text-band-ink">
        <div className={cn(measure, 'flex min-h-16 items-center gap-7 max-[782px]:gap-3')}>
          <a href={s.urls.overview} onClick={(e) => { e.preventDefault(); go('overview'); }} className="inline-flex items-center text-band-ink hover:text-white"><Logo which="wordmark" className="[&>svg]:h-7 [&>svg]:w-[138px]" /></a>
          <nav className="flex self-stretch gap-[22px] max-[782px]:gap-3" aria-label={__('MonoRanks sections', 'monoranks')}>
            {sections.map((e) => (
              <a key={e.id} href={e.href} onClick={(ev) => { ev.preventDefault(); go(e.id); }} aria-current={section === e.id ? 'page' : undefined}
                className={cn('inline-flex items-center border-b-2 pt-[2px] text-[13px] font-medium', section === e.id ? 'border-brand text-band-ink' : 'border-transparent text-band-muted hover:text-white')}>{e.label}</a>
            ))}
          </nav>
          <div className="ms-auto flex items-center gap-1.5">
            <a href={out(s.urls.docs, 'header-help', section)} target="_blank" rel="noopener" className="inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-[12.5px] font-medium text-band-muted hover:bg-band-edge hover:text-white">{__('Help', 'monoranks')}</a>
            <a href={out(s.urls.app, 'header-open', section)} target="_blank" rel="noopener" className="inline-flex h-8 items-center gap-1.5 rounded-md border border-band-edge px-3 text-[12.5px] font-medium text-band-ink hover:bg-band-edge hover:text-white max-[782px]:hidden">{__('Open MonoRanks', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a>
          </div>
        </div>
      </header>
      <div className={cn(measure, 'flex flex-wrap items-start justify-between gap-x-6 gap-y-3 pb-1.5 pt-[30px]')}>
        <div className="flex min-w-0 flex-[1_1_420px] flex-col gap-1.5">
          <h1 className="text-[26px] font-semibold tracking-[-0.02em] leading-tight text-ink">{title}</h1>
          {description && <p className="max-w-[72ch] text-ink2">{description}</p>}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2 pt-1">{actions}</div>}
      </div>
      {/* WordPress moves its own notices to just before this marker; anything it leaves behind is collected below. */}
      <div className="wp-header-end" />
      <Notices className={cn(measure, 'flex flex-col gap-2.5 pt-3')} />
      <main className={cn(measure, 'flex flex-[1_0_auto] flex-col gap-5 pb-10 pt-[22px]')}>{children}</main>
      <Footer section={section} go={go} />
    </>
  );
}

function Footer({ section, go }: { section: Screen; go: (next: Screen) => void }) {
  const s = adminSettings();
  return (
    <footer className="mt-auto bg-band text-band-ink">
      <div className={cn(measure, 'grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-8 py-7 max-[960px]:grid-cols-[minmax(0,1fr)_auto] max-[960px]:gap-5 max-[782px]:grid-cols-1')}>
        <div className="inline-flex items-center gap-2.5"><Logo which="wordmark" className="[&>svg]:h-[26px] [&>svg]:w-[128px]" /><span className="rounded-md border border-band-edge px-[7px] py-[2px] text-[11px] font-medium text-band-muted">v{s.version}</span></div>
        <div className="min-w-0 border-s border-band-edge ps-7 max-[960px]:col-start-1 max-[960px]:border-0 max-[960px]:ps-0">
          <span className="mb-1.5 block text-[11px] uppercase tracking-[.08em] text-band-muted">{__('Your data', 'monoranks')}</span>
          <a href={s.urls.settings} onClick={(e) => { e.preventDefault(); go('settings'); }} className="inline-flex items-center gap-[7px] text-[13px] text-band-ink hover:text-white hover:underline hover:underline-offset-4">{__('What is sent and what can change', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a>
        </div>
        <a href={out(s.urls.docs, 'footer-help', section)} target="_blank" rel="noopener" className="block rounded-[9px] border border-band-edge px-[18px] py-3 text-start text-band-ink hover:bg-band-edge hover:text-white max-[960px]:col-start-2 max-[960px]:row-span-2 max-[960px]:row-start-1 max-[782px]:col-auto max-[782px]:row-auto">
          <span className="mb-1.5 block text-[11px] text-band-muted">{__('Need a hand?', 'monoranks')}</span>
          <strong className="flex items-center justify-between gap-5 text-[13px] font-medium">{__('Help and resources', 'monoranks')}<ArrowRight size={14} className="mr-flip" /></strong>
        </a>
      </div>
      <div className={cn(measure)}>
        <div className="flex items-center justify-center gap-3 border-t border-band-edge pb-[18px] pt-4 text-[11px] text-band-muted">
          <span>{__('A product by', 'monoranks')}</span>
          <a href={out('https://veronalabs.com/', 'footer-publisher', section)} target="_blank" rel="noopener" aria-label="VeronaLabs" className="inline-flex min-h-7 items-center text-band-muted opacity-75 hover:opacity-100 hover:text-band-muted"><Logo which="veronalabs" className="[&>svg]:h-auto [&>svg]:w-28" /></a>
        </div>
      </div>
    </footer>
  );
}

/** The result of an action. Keeps WordPress's notice classes (the browser tests read them) with the app's card look. */
export function NoticeBox({ notice }: { notice: Notice | null }) {
  if (!notice) return null;
  const tone = { success: 'text-good-ink', error: 'text-critical', warning: 'text-warn' }[notice.type];
  const Icon = { success: CheckCircle2, error: AlertCircle, warning: AlertTriangle }[notice.type];
  return (
    <div className={cn('notice', `notice-${notice.type}`, 'flex items-start gap-2.5')} role="status">
      <Icon size={16} className={cn('mt-[1px] shrink-0', tone)} aria-hidden="true" />
      <p className="text-ink">{notice.text}</p>
    </div>
  );
}

/** Label / value pairs, 150px label column. */
export function KV({ rows }: { rows: [string, ReactNode][] }) {
  return (
    <div className="grid grid-cols-[150px_minmax(0,1fr)] gap-x-3 gap-y-2 text-[12px] max-[960px]:grid-cols-1">
      {rows.map(([k, v], i) => (
        <div key={i} className="contents"><div className="text-mute max-[960px]:mt-1">{k}</div><div className="min-w-0 [overflow-wrap:anywhere]">{v}</div></div>
      ))}
    </div>
  );
}

export function Empty({ title, text }: { title: string; text: string }) {
  return <div className="flex flex-col items-center gap-2 px-6 py-9 text-center text-ink2"><span className="text-[13px] font-semibold text-ink">{title}</span><span className="text-[12px]">{text}</span></div>;
}
