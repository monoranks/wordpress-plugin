import { Fragment, useEffect, useState, type ReactNode } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ArrowUpRight, ChevronRight } from 'lucide-react';
import { adminSettings, type Notice } from '@/settings';
import { api, errorText, type OverviewData } from '@/lib/api';
import { Shell, NoticeBox, KV, Empty } from '@/shell/Shell';
import { Ring, Delta, Sparkline, scoreWord, shortDate, tone } from '@/shell/Ring';
import { ChangeLog, th, td } from '@/shell/ChangeLog';
import { Card, CardHeader, CardTitle, CardBody, Tile, TileLabel, TileNumber } from '@/components/ui/card';
import { out } from '@/lib/links';
import type { Screen } from '@/lib/screen';
import { fmt } from '@/lib/format';
import { Button } from '@/components/ui/button';
import { Badge, Dot, SeverityPill, severityOf } from '@/components/ui/badge';
import { Tip } from '@/components/ui/tip';
import { GrowCards } from '@/screens/Grow';
import { VideoCard } from '@/screens/VideoCard';

function excerpt(s: string, n: number) {
  const t = s.replace(/<[^>]+>/g, '').trim();
  return t.length > n ? t.slice(0, n - 1) + '…' : t;
}

type Fix = NonNullable<OverviewData['overview']>['ready'][number];

/** Where a fix lands, in one short line. */
function fixWhere(fix: Fix) {
  return fix.title || (fix.field === 'redirect' ? fix.from : __('Whole site', 'monoranks'));
}

/** The opened part of a fix: old and new value side by side, so the same text never shows twice without saying which is which. */
function FixDetail({ fix }: { fix: Fix }) {
  if (fix.field === 'content') {
    return <p className="text-[14px] text-ink2">{__('Adds one short answer at the top of the page. See it first in MonoRanks.', 'monoranks')}</p>;
  }
  return (
    <div className="grid grid-cols-2 gap-2.5 max-[600px]:grid-cols-1">
      <div className="flex min-w-0 flex-col gap-1.5 rounded-xl bg-surface2 px-4 py-3">
        <TileLabel>{__('Now', 'monoranks')}</TileLabel>
        <span className="text-[14px] text-ink2 [overflow-wrap:anywhere]"><bdi>{fix.before ? excerpt(fix.before, 160) : __('(empty)', 'monoranks')}</bdi></span>
      </div>
      <div className="flex min-w-0 flex-col gap-1.5 rounded-xl bg-surface2 px-4 py-3">
        <TileLabel>{__('After', 'monoranks')}</TileLabel>
        <span className="text-[14px] font-semibold text-ink [overflow-wrap:anywhere]"><bdi>{excerpt(fix.after, 200)}</bdi></span>
      </div>
      {(fix.image || fix.note) && <span className="col-span-full text-[13px] text-mute"><bdi>{fix.field === 'alt' && fix.image ? sprintf(__('Image: %s', 'monoranks'), fix.image) : fix.note}</bdi></span>}
    </div>
  );
}

/** A list of fixes, one line each (what and where), with the action on the right; a click opens old and new. */
function FixList({ fixes, labels, action }: { fixes: Fix[]; labels: Record<string, string>; action: (fix: Fix) => ReactNode }) {
  const [open, setOpen] = useState<string | null>(null);
  return (
    <ul className="m-0 flex list-none flex-col p-0">
      {fixes.map((fix) => {
        const isOpen = open === fix.id;
        const id = `mr-fix-${fix.id}`;
        return (
          <li key={fix.id} className="border-b border-grid last:border-b-0">
            <div className="flex min-h-[60px] items-center gap-3 py-2.5">
              <button type="button" aria-expanded={isOpen} aria-controls={id} onClick={() => setOpen(isOpen ? null : fix.id)} className="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 border-0 bg-transparent p-0 text-start">
                <ChevronRight size={15} className={`mr-flip shrink-0 text-mute transition-transform ${isOpen ? 'rotate-90' : ''}`} aria-hidden="true" />
                <Badge>{labels[fix.field] ?? fix.field}</Badge>
                <span className="min-w-0 truncate text-[14px] font-semibold text-ink"><bdi>{fixWhere(fix)}</bdi></span>
              </button>
              {action(fix)}
            </div>
            {isOpen && <div id={id} className="pb-4 ps-[25px]"><FixDetail fix={fix} /></div>}
          </li>
        );
      })}
    </ul>
  );
}

/** MonoRanks → Overview: the site's scores, what to do next, pages needing attention, fixes ready to apply, recent changes. */
export function Overview({ go }: { go: (next: Screen) => void }) {
  const s = adminSettings();
  const [data, setData] = useState<OverviewData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<Notice | null>(s.notice);
  const [busy, setBusy] = useState<string | null>(null);
  const [undoing, setUndoing] = useState<number | null>(null);

  useEffect(() => {
    api.overview().then(setData).catch((e) => setError(errorText(e, __('Could not load the overview.', 'monoranks'))));
  }, []);

  async function run<T extends { ok: boolean; notice: Notice; data: unknown }>(key: string, call: () => Promise<T>) {
    setBusy(key);
    try {
      const r = await call();
      setNotice(r.notice);
      setData(r.data as OverviewData);
      window.scrollTo({ top: 0 });
    } catch (e) {
      setNotice({ type: 'error', text: errorText(e, __('Something went wrong. Try again.', 'monoranks')) });
    } finally {
      setBusy(null);
      setUndoing(null);
    }
  }

  const o = data?.overview ?? null;
  // Fixes MonoRanks could write once someone approves them there; only shown when there are some.
  // A newer MonoRanks also says how many still need a value there (typed or drafted); older ones leave it out.
  const need = typeof o?.to_review?.needs_value === 'number' ? o.to_review.needs_value : null;
  const review = o?.to_review && (o.to_review.total > 0 || (need ?? 0) > 0) && o.to_review.review_url ? o.to_review : null;
  // Score changes cover the week before the last check: "+3 since Oct 25".
  const since = o?.audited_at ? shortDate(new Date(new Date(o.audited_at).getTime() - 7 * 86400000)) : '';
  const description = data
    ? o
      ? <><bdi>{data.host}</bdi>{o.audited_at && <> · <Tip text={data.audited}><span>{sprintf(__('Checked %s', 'monoranks'), shortDate(o.audited_at))}</span></Tip></>}</>
      : undefined
    : ' ';

  const video = data?.video ? <VideoCard video={data.video} onDismiss={() => { setData({ ...data, video: null }); api.dismissVideo().catch(() => undefined); }} /> : null;

  const actions = data?.connected && !data.revoked ? (
    <Tip align="end" text={__('Sends your published pages to MonoRanks now. It also happens by itself.', 'monoranks')}>
      <Button variant="primary" disabled={busy !== null} aria-busy={busy === 'sync'} onClick={() => run('sync', () => api.sync('overview'))}>{__('Send content', 'monoranks')}</Button>
    </Tip>
  ) : null;

  return (
    <Shell section="overview" go={go} title={__('Overview', 'monoranks')} description={description} actions={actions}>
      <NoticeBox notice={notice} />
      {error && <NoticeBox notice={{ type: 'error', text: error }} />}
      {!data && !error && <Skeleton />}
      {data && (!data.connected || data.revoked) && <><ConnectSteps revoked={data.revoked} go={go} />{!o && video}</>}
      {data && data.connected && !data.revoked && !o && <><Waiting data={data} />{video}<GrowCards /></>}
      {data && o && (
        <>
          <Card className="@container flex flex-col gap-5 px-7 py-7 max-[782px]:px-[18px] max-[782px]:py-5">
            {/* A key that no longer works: the scores are the last ones received, and the screen says so. */}
            {data.revoked && <div><Badge variant="warn">{data.updated ? sprintf(__('Old data · Updated %s', 'monoranks'), data.updated) : __('Old data', 'monoranks')}</Badge></div>}
            <div className="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-8 @max-[1000px]:grid-cols-1 @max-[1000px]:gap-6">
              <div className="flex min-w-0 items-center gap-6">
                <Ring score={o.health} size="xl" label={undefined} />
                <div className="flex min-w-0 flex-col items-start gap-1">
                  <TileLabel>{__('Site health', 'monoranks')}<Tip text={sprintf(_n('Average of %1$s page out of %2$s published.', 'Average of %1$s pages out of %2$s published.', o.pages_scored, 'monoranks'), fmt(o.pages_scored), fmt(o.pages_total))} /></TileLabel>
                  <span className={`text-[30px] font-bold leading-tight tracking-[-0.02em] ${{ good: 'text-good-ink', warn: 'text-ink', critical: 'text-critical', none: 'text-mute' }[tone(o.health)]}`}>{scoreWord(o.health)}</span>
                  <Delta n={o.deltas.health} since={since} />
                </div>
              </div>
              <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,190px),1fr))] gap-3">
                <Tile className="justify-between">
                  <TileLabel>{__('AI answers ready', 'monoranks')}<Tip text={__('How easily AI answers can quote your pages.', 'monoranks')} /></TileLabel>
                  <div className="flex items-center gap-3"><Ring score={o.aeo} size="lg" /><Delta n={o.deltas.aeo} since={since} /></div>
                </Tile>
                <Tile className="justify-between">
                  <TileLabel>{__('Search clicks', 'monoranks')}<Tip text={o.traffic ? __('Clicks from Google in the last 28 days.', 'monoranks') : __('Connect Google Search Console in MonoRanks to see clicks here.', 'monoranks')} /></TileLabel>
                  {o.traffic ? (
                    <>
                      <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1"><TileNumber n={o.traffic.clicks_28d} /><Delta n={o.traffic.delta_pct} unit="%" period="previous28" /></div>
                      <Sparkline series={o.traffic.series} />
                    </>
                  ) : <span className="text-[32px] font-[750] leading-none text-mute">—</span>}
                </Tile>
                <Tile className="justify-between">
                  <TileLabel>{__('Fixes applied', 'monoranks')}<Tip text={[o.ai.bots_rules ? __('AI tool rules on', 'monoranks') : __('No AI tool rules', 'monoranks'), o.ai.llms_txt ? __('AI site summary published', 'monoranks') : __('No AI site summary', 'monoranks')].join(' · ')} /></TileLabel>
                  <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1"><TileNumber n={o.fixes.applied_30d} /><span className="text-[13px] font-semibold text-mute">{__('30 days', 'monoranks')}</span></div>
                  {o.fixes.ready > 0 ? <span className="text-[13px] font-semibold text-brand-ink">{sprintf(_n('%s ready', '%s ready', o.fixes.ready, 'monoranks'), fmt(o.fixes.ready))}</span> : <span className="text-[13px] text-mute">{__('Nothing waiting', 'monoranks')}</span>}
                </Tile>
              </div>
            </div>
          </Card>

          {video}

          {!data.revoked && <GrowCards />}

          {!data.revoked && <div className="grid grid-cols-[minmax(0,3fr)_minmax(0,2fr)] items-start gap-6 max-[1100px]:grid-cols-1">
            <Card>
              <CardHeader><CardTitle>{__('Pages to fix', 'monoranks')}{o.attention.length > 0 && <Badge>{fmt(o.attention.length)}</Badge>}</CardTitle><Button variant="ghost" size="sm" asChild><a href={out(data.app_url, 'all-pages')} target="_blank" rel="noopener">{__('All pages', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a></Button></CardHeader>
              {o.attention.length ? (
                <div className="mt-3.5 overflow-x-auto rounded-b-2xl">
                  <table className="w-full table-fixed border-collapse">
                    <colgroup><col /><col className="w-[84px] max-[600px]:w-[52px]" /><col className="w-[92px] max-[600px]:w-[52px]" /><col className="w-[104px] max-[600px]:w-[98px]" /></colgroup>
                    <thead><tr><th className={th}>{__('Page', 'monoranks')}</th><th className={`${th} max-[600px]:px-1 max-[600px]:text-[0px]`}><span className="max-[600px]:hidden">{__('Health', 'monoranks')}</span><Tip text={__('Health', 'monoranks')} className="min-[601px]:hidden" /></th><th className={`${th} whitespace-normal max-[600px]:px-1`}><span className="inline-flex flex-wrap items-center gap-1"><span className="max-[600px]:hidden">{__('AI', 'monoranks')}</span><Tip text={__('AI answers ready', 'monoranks')} /></span></th><th className={th}><span className="sr-only">{__('Fix', 'monoranks')}</span></th></tr></thead>
                    <tbody>
                      {o.attention.map((row) => (
                        <AttentionRow key={row.post_id + row.url} row={row} />
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : <Empty title={__('Nothing to fix right now', 'monoranks')} text={__('Every checked page scores 60 or more.', 'monoranks')} />}
            </Card>

            <div className="flex min-w-0 flex-col gap-6">
              <Card>
                <CardHeader>
                  <CardTitle>{__('Ready to apply', 'monoranks')}{o.ready.length > 0 && <Badge>{fmt(o.ready.length)}</Badge>}{o.ready.length > 0 && <Tip text={__('Approved in MonoRanks. Each one can be undone for 30 days.', 'monoranks')} />}</CardTitle>
                  {o.ready.length > 1 && <Button variant="primary" size="sm" disabled={busy !== null} aria-busy={busy === 'all'} onClick={() => run('all', () => api.apply('all'))}>{__('Apply all', 'monoranks')}</Button>}
                </CardHeader>
                <CardBody className="flex flex-col pt-2">
                  {o.ready.length === 0 && review && (
                    <div className="flex flex-col gap-4 py-2">
                      <div className="flex flex-wrap items-center gap-1.5">
                        <b className="text-[15px] font-bold">{need === null ? sprintf(_n('%s fix to approve', '%s fixes to approve', review.total, 'monoranks'), fmt(review.total)) : [review.total > 0 && sprintf(_n('%s ready to apply', '%s ready to apply', review.total, 'monoranks'), fmt(review.total)), need > 0 && sprintf(_n('%s needs a value', '%s need a value', need, 'monoranks'), fmt(need))].filter(Boolean).join(' · ')}</b>
                        <Tip text={need !== null && need > 0 ? __('Type a value or draft one with AI in MonoRanks, approve it, and it is written here, with undo.', 'monoranks') : __('Approve them in MonoRanks and they are written here, with undo.', 'monoranks')} />
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        {Object.entries(review.by_field).sort((a, b) => b[1] - a[1]).map(([field, n]) => <Badge key={field} variant="pill"><bdi>{data.labels[field] ?? field}</bdi><span className="tabular-nums text-ink2">{fmt(n)}</span></Badge>)}
                      </div>
                      <div><Button variant="primary" asChild><a href={out(review.review_url, 'review-fixes')} target="_blank" rel="noopener">{__('Review fixes', 'monoranks')}<ArrowUpRight size={15} className="mr-flip" /></a></Button></div>
                    </div>
                  )}
                  {o.ready.length === 0 && !review && <Empty title={__('No fixes waiting', 'monoranks')} text={__('Fixes you approve in MonoRanks appear here, ready to write into WordPress.', 'monoranks')} />}
                  {o.ready.length > 0 && (
                    <FixList fixes={o.ready} labels={data.labels} action={(fix) => fix.field === 'content' && fix.page_url ? (
                      <Button size="sm" asChild><a href={out(fix.page_url, 'review-fix')} target="_blank" rel="noopener">{__('Review', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a></Button>
                    ) : (
                      <Button size="sm" disabled={busy !== null} aria-busy={busy === fix.id} onClick={() => run(fix.id, () => api.apply(fix.id))}>{__('Apply', 'monoranks')}</Button>
                    )} />
                  )}
                </CardBody>
              </Card>
              {(o.needs_review ?? []).length > 0 && (
                <Card>
                  <CardHeader><CardTitle>{__('Check in MonoRanks', 'monoranks')}<Badge>{fmt(o.needs_review.length)}</Badge><Tip text={__('Changed in WordPress after you approved them, so they are not applied from here.', 'monoranks')} /></CardTitle></CardHeader>
                  <CardBody className="flex flex-col pt-2">
                    <FixList fixes={o.needs_review} labels={data.labels} action={(fix) => <Button size="sm" asChild><a href={out(fix.page_url || o.site_url, 'review-fix')} target="_blank" rel="noopener">{__('Review', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a></Button>} />
                  </CardBody>
                </Card>
              )}
            </div>
          </div>}

          <Card>
            <CardHeader><CardTitle>{__('Recent changes', 'monoranks')}</CardTitle><Button variant="ghost" size="sm" asChild><a href={s.urls.settings} onClick={(e) => { e.preventDefault(); go('settings'); }}>{__('All changes', 'monoranks')}</a></Button></CardHeader>
            <div className="pt-3.5"><ChangeLog rows={data.log} busy={undoing} onUndo={(row) => { setUndoing(row.index); run('undo', () => api.undo(row, 'overview')); }} /></div>
          </Card>
        </>
      )}
    </Shell>
  );
}

type Attention = NonNullable<OverviewData['overview']>['attention'][number];

/** One page to fix: title with its severity, the two scores, and the action. A click opens the biggest problem. */
function AttentionRow({ row }: { row: Attention }) {
  const [open, setOpen] = useState(false);
  const id = `mr-page-${row.post_id}`;
  return (
    <Fragment>
      <tr className={`group/row ${open ? '[&>td]:bg-tint' : 'hover:[&>td]:bg-hover'}`}>
        <td className={td}>
          <button type="button" aria-expanded={open} aria-controls={id} onClick={() => setOpen(!open)} className="flex w-full min-w-0 cursor-pointer items-center gap-2 border-0 bg-transparent p-0 text-start" title={row.url}>
            <ChevronRight size={15} className={`mr-flip shrink-0 text-mute transition-transform ${open ? 'rotate-90' : ''}`} aria-hidden="true" />
            <span className="min-w-0 truncate text-[14px] font-semibold text-ink"><bdi>{row.title || row.url}</bdi></span>
            <span className="inline-flex shrink-0 max-[600px]:hidden"><SeverityPill level={severityOf(row.health)} /></span>
          </button>
        </td>
        <td className={`${td} max-[600px]:px-1`}><Ring score={row.health} /></td>
        <td className={`${td} max-[600px]:px-1`}><Ring score={row.aeo} /></td>
        <td className={`${td} text-end`}>{row.page_url && <Button size="sm" asChild><a href={out(row.page_url, 'fix-page')} target="_blank" rel="noopener">{__('Fix', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a></Button>}</td>
      </tr>
      {open && (
        <tr id={id}>
          <td colSpan={4} className="border-b border-grid bg-surface px-7 pb-5 pt-3 max-[782px]:px-[18px]">
            <div className="flex flex-col gap-1.5 rounded-xl bg-surface2 px-4 py-3">
              <TileLabel>{__('Biggest problem', 'monoranks')}</TileLabel>
              <span className="text-[14px] font-semibold text-ink">{row.issue || '—'}</span>
            </div>
          </td>
        </tr>
      )}
    </Fragment>
  );
}

function Skeleton() {
  return <Card className="h-[180px] animate-pulse bg-surface2" />;
}

export function ConnectSteps({ revoked, go }: { revoked: boolean; go: (next: Screen) => void }) {
  const s = adminSettings();
  return (
    <Card>
      <CardHeader><CardTitle>{revoked ? __('Connect MonoRanks again', 'monoranks') : __('Connect MonoRanks', 'monoranks')}</CardTitle><Badge variant="pill"><Dot tone={revoked ? 'critical' : 'muted'} />{revoked ? __('Key revoked', 'monoranks') : __('Not connected', 'monoranks')}</Badge></CardHeader>
      <CardBody className="flex flex-col gap-6">
        <Step n={1}><b className="text-[15px] font-bold text-ink">{__('Connect to MonoRanks', 'monoranks')}</b><span className="text-[14px] text-mute">{__('Sign in and approve. Takes a minute.', 'monoranks')}</span><div className="pt-1.5"><Button variant="primary" asChild><a href={out(s.urls.connect, 'connect-step')} target="_self">{__('Connect', 'monoranks')}</a></Button></div></Step>
        <Step n={2} later><span className="inline-flex items-center gap-1.5"><b className="text-[15px] font-bold text-ink">{__('Or paste a connector key', 'monoranks')}</b><Tip text={__('Use this when your host blocks Application Passwords.', 'monoranks')} /></span><div className="pt-1.5"><Button asChild><a href={s.urls.settings} onClick={(e) => { e.preventDefault(); go('settings'); }}>{__('Open settings', 'monoranks')}</a></Button></div></Step>
        {!revoked && <Step n={3} later><b className="text-[15px] font-bold text-ink">{__('See scores here and in your Posts list', 'monoranks')}</b></Step>}
      </CardBody>
    </Card>
  );
}

export function Step({ n, later, children }: { n: number; later?: boolean; children: ReactNode }) {
  return <div className="flex items-start gap-4"><span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[14px] font-bold ${later ? 'bg-surface2 text-ink2' : 'bg-brand text-white'}`}>{n}</span><div className="flex min-w-0 flex-1 flex-col gap-1 pt-1">{children}</div></div>;
}

function Waiting({ data }: { data: OverviewData }) {
  const kind = data.status;
  const [short, results] = kind === 'unsupported'
    ? [__('Not shared yet', 'monoranks'), __('This MonoRanks does not share results with the plugin yet. It asks again tomorrow.', 'monoranks')]
    : kind === 'error'
      ? [__('MonoRanks not reached', 'monoranks'), __('Tried again within the hour, or when you reload this screen.', 'monoranks')]
      : [__('Not checked yet', 'monoranks'), __('Scores appear here and in the Posts list after the first weekly check.', 'monoranks')];
  return (
    <Card>
      <CardHeader><CardTitle>{__('Waiting for the first check', 'monoranks')}</CardTitle><Badge variant="pill"><Dot tone="good" />{__('Connected', 'monoranks')}</Badge></CardHeader>
      <CardBody className="flex flex-col gap-5">
        <KV rows={[
          [__('Content', 'monoranks'), data.last_sent ? sprintf(_n('%1$s published item sent %2$s', '%1$s published items sent %2$s', data.items, 'monoranks'), fmt(data.items), data.last_sent) : data.send_failed && !data.sending ? __('Not sent. Use "Send content".', 'monoranks') : <span className="text-mute">{__('Sending…', 'monoranks')}</span>],
          [__('Results', 'monoranks'), <span className="flex flex-wrap items-center gap-2">{short}<Tip text={data.fetched ? <>{results} · {sprintf(__('checked %s', 'monoranks'), data.fetched)}</> : results} /></span>],
        ]} />
        <div><Button asChild><a href={out(data.app_url, 'open-audit')} target="_blank" rel="noopener">{__('Open MonoRanks', 'monoranks')}<ArrowUpRight size={15} className="mr-flip" /></a></Button></div>
      </CardBody>
    </Card>
  );
}
