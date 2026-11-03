import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { ArrowUpRight, CheckCircle2, AlertCircle, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { adminSettings, type Notice } from '@/settings';
import { Notices } from '@/shell/Notices';
import { cn } from '@/lib/utils';
import { out } from '@/lib/links';
import { Tip } from '@/components/ui/tip';
import { VideoButton } from '@/shell/VideoButton';
import type { Screen } from '@/lib/screen';

/** The plugin's own SVG (assets/*.svg), inlined so currentColor applies. Static files shipped with the plugin. */
export function Logo({ which, className }: { which: 'wordmark' | 'mark' | 'veronalabs'; className?: string }) {
  return <span className={cn('inline-flex [&>svg]:block', className)} dangerouslySetInnerHTML={{ __html: adminSettings().logos[which] }} />;
}

const measure = 'mx-auto w-full max-w-[1260px] px-10 max-[960px]:px-6 max-[782px]:px-4';

/**
 * The frame, in the app's redesign: a light top bar (logo, section tabs with an accent line under the chosen one, help,
 * a way into MonoRanks), the title, the work area on the grey page, and one quiet footer line.
 */
export function Shell({ section, go, title, description, actions, children }: { section: Screen; go: (next: Screen) => void; title: string; description?: ReactNode; actions?: ReactNode; children: ReactNode }) {
  const s = adminSettings();
  const sections: { id: Screen; label: string; href: string }[] = [
    { id: 'overview', label: __('Overview', 'monoranks'), href: s.urls.overview },
    { id: 'settings', label: __('Settings', 'monoranks'), href: s.urls.settings },
  ];
  return (
    <>
      <header className="bg-surface shadow-[inset_0_-1px_0_var(--border)]">
        <div className={cn(measure, 'flex min-h-[68px] items-center gap-8 max-[782px]:gap-3 max-[480px]:gap-1.5')}>
          <a href={s.urls.overview} onClick={(e) => { e.preventDefault(); go('overview'); }} className="inline-flex items-center text-ink hover:text-ink"><Logo which="wordmark" className="[&>svg]:h-7 [&>svg]:w-[138px] max-[480px]:[&>svg]:w-[104px]" /></a>
          <nav className="flex self-stretch gap-2 max-[782px]:gap-0" aria-label={__('MonoRanks sections', 'monoranks')}>
            {sections.map((e) => (
              <a key={e.id} href={e.href} onClick={(ev) => { ev.preventDefault(); go(e.id); }} aria-current={section === e.id ? 'page' : undefined}
                className={cn('inline-flex items-center border-b-[3px] px-3 pt-[3px] text-[15px] max-[480px]:px-2 max-[480px]:text-[14px]', section === e.id ? 'border-brand font-bold text-ink hover:text-ink' : 'border-transparent font-semibold text-ink2 hover:text-ink')}>{e.label}</a>
            ))}
          </nav>
          <div className="ms-auto flex items-center gap-1">
            <VideoButton />
            <Button variant="ghost" size="sm" asChild><a href={out(s.urls.docs, 'header-help', section)} target="_blank" rel="noopener">{__('Help', 'monoranks')}</a></Button>
            <Button size="sm" asChild className="max-[782px]:hidden"><a href={out(s.urls.app, 'header-open', section)} target="_blank" rel="noopener">{__('Open MonoRanks', 'monoranks')}<ArrowUpRight size={15} className="mr-flip" /></a></Button>
          </div>
        </div>
      </header>
      <div className={cn(measure, 'flex flex-wrap items-center justify-between gap-x-6 gap-y-3 pt-9')}>
        <div className="flex min-w-0 flex-[1_1_420px] flex-col gap-1.5">
          <h1 className="text-[30px] font-bold leading-tight tracking-[-0.025em] text-ink">{title}</h1>
          {description && <p className="text-[14px] text-mute">{description}</p>}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
      </div>
      {/* WordPress moves its own notices to just before this marker; anything it leaves behind is collected below. */}
      <div className="wp-header-end" />
      <Notices className={cn(measure, 'flex flex-col gap-2.5 pt-4')} />
      <main className={cn(measure, 'flex flex-[1_0_auto] flex-col gap-7 pb-12 pt-7')}>{children}</main>
      <Footer section={section} go={go} />
    </>
  );
}

function Footer({ section, go }: { section: Screen; go: (next: Screen) => void }) {
  const s = adminSettings();
  const link = 'inline-flex items-center gap-1.5 text-[13px] font-semibold text-ink2 hover:text-brand-ink';
  return (
    <footer className="mt-auto">
      <div className={cn(measure, 'flex flex-wrap items-center gap-x-7 gap-y-3 border-t border-border py-6 text-mute')}>
        <span className="inline-flex items-center gap-2.5 text-ink"><Logo which="wordmark" className="[&>svg]:h-[22px] [&>svg]:w-[108px]" /><span className="text-[12px] font-semibold text-mute">v{s.version}</span></span>
        <a href={s.urls.settings} onClick={(e) => { e.preventDefault(); go('settings'); }} className={link}>{__('What is sent', 'monoranks')}</a>
        <a href={out(s.urls.docs, 'footer-help', section)} target="_blank" rel="noopener" className={link}>{__('Help', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a>
        <a href={out('https://veronalabs.com/', 'footer-publisher', section)} target="_blank" rel="noopener" aria-label={__('A product by VeronaLabs', 'monoranks')} className="ms-auto inline-flex min-h-7 items-center gap-2 text-[12px] text-mute opacity-80 hover:text-mute hover:opacity-100">{__('A product by', 'monoranks')}<Logo which="veronalabs" className="[&>svg]:h-auto [&>svg]:w-24" /></a>
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
    <div className={cn('notice', `notice-${notice.type}`, 'flex items-start gap-3')} role="status">
      <Icon size={18} className={cn('mt-[1px] shrink-0', tone)} aria-hidden="true" />
      <p className="font-semibold text-ink">{notice.text}</p>
    </div>
  );
}

/** Label / value pairs, 150px label column. */
export function KV({ rows }: { rows: [string, ReactNode][] }) {
  return (
    <div className="grid grid-cols-[150px_minmax(0,1fr)] gap-x-4 gap-y-2.5 text-[14px] max-[960px]:grid-cols-1">
      {rows.map(([k, v], i) => (
        <div key={i} className="contents"><div className="font-semibold text-mute max-[960px]:mt-1">{k}</div><div className="min-w-0 [overflow-wrap:anywhere]">{v}</div></div>
      ))}
    </div>
  );
}

/** An empty state: one bold line and, behind an (i), what fills it. */
export function Empty({ title, text }: { title: string; text?: string }) {
  return <div className="flex items-center justify-center gap-1.5 px-6 py-12 text-center"><span className="text-[15px] font-semibold text-ink2">{title}</span>{text && <Tip text={text} />}</div>;
}
