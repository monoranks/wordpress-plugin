import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { adminSettings, type Notice } from '@/settings';
import { api, errorText, type OverviewData } from '@/lib/api';
import { Shell, NoticeBox, KV, Empty } from '@/shell/Shell';
import { Ring, Delta, Sparkline } from '@/shell/Ring';
import { ChangeLog } from '@/shell/ChangeLog';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/card';
import { out } from '@/lib/links';
import type { Screen } from '@/lib/screen';
import { fmt } from '@/lib/format';
import { Button } from '@/components/ui/button';
import { Badge, Dot } from '@/components/ui/badge';

const th = 'whitespace-nowrap border-b border-border px-[10px] py-[11px] text-start text-[12px] font-medium text-mute';
const td = 'border-b border-grid px-[10px] py-3 align-middle group-last:border-b-0';

function excerpt(s: string, n: number) {
  const t = s.replace(/<[^>]+>/g, '').trim();
  return t.length > n ? t.slice(0, n - 1) + '…' : t;
}

function relativePath(url: string) {
  try { const u = new URL(url); return u.pathname + u.search; } catch { return url; }
}

/** MonoRanks → Overview: site scores, search clicks, pages needing attention, fixes ready to apply, recent changes. */
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
  const description = data
    ? o
      ? <>{__('Weekly SEO, AEO and GEO audit of', 'monoranks')} <code>{data.host}</code>{data.audited && <> · {sprintf(__('last audit %s', 'monoranks'), data.audited)}</>}{o.pages_total > 0 && <> · {sprintf(__('%1$s of %2$s published pages scored', 'monoranks'), fmt(o.pages_scored), fmt(o.pages_total))}</>}</>
      : __('Weekly SEO, AEO and GEO audits, with the fixes you approve written into WordPress.', 'monoranks')
    : ' ';

  const actions = data?.connected ? (
    <Button variant="primary" disabled={busy !== null} aria-busy={busy === 'sync'} onClick={() => run('sync', () => api.sync('overview'))}>{__('Sync content now', 'monoranks')}</Button>
  ) : null;

  return (
    <Shell section="overview" go={go} title={__('Overview', 'monoranks')} description={description} actions={actions}>
      <NoticeBox notice={notice} />
      {error && <NoticeBox notice={{ type: 'error', text: error }} />}
      {!data && !error && <Skeleton />}
      {data && (!data.connected || data.revoked) && <ConnectSteps revoked={data.revoked} go={go} />}
      {data && data.connected && !data.revoked && !o && <Waiting data={data} />}
      {data && o && (
        <>
          <div className="grid grid-cols-4 gap-[14px] max-[960px]:grid-cols-2 max-[600px]:grid-cols-1">
            <Card className="flex flex-col gap-2.5 px-5 py-[18px]">
              <div className="flex items-center justify-between"><span className="text-[12px] font-medium text-ink2">{__('Health score', 'monoranks')}</span><Delta n={o.deltas.health} /></div>
              <div className="flex items-center gap-[14px]"><Ring score={o.health} size="lg" /><div className="flex flex-col gap-0.5"><span className="text-[12px] text-ink2">{__('Technical, on-page, links, speed', 'monoranks')}</span><span className="text-[11px] text-mute">{sprintf(_n('Site average, %s page', 'Site average, %s pages', o.pages_scored, 'monoranks'), fmt(o.pages_scored))}</span></div></div>
            </Card>
            <Card className="flex flex-col gap-2.5 px-5 py-[18px]">
              <div className="flex items-center justify-between"><span className="text-[12px] font-medium text-ink2">{__('AEO score', 'monoranks')}</span><Delta n={o.deltas.aeo} /></div>
              <div className="flex items-center gap-[14px]"><Ring score={o.aeo} size="lg" /><div className="flex flex-col gap-0.5"><span className="text-[12px] text-ink2">{__('Answer-first, structure, entities', 'monoranks')}</span><span className="text-[11px] text-mute">{__('How well AI answers can use your pages', 'monoranks')}</span></div></div>
            </Card>
            <Card className="flex flex-col gap-2.5 px-5 py-[18px]">
              <div className="flex items-center justify-between"><span className="text-[12px] font-medium text-ink2">{__('Search clicks, 28 days', 'monoranks')}</span>{o.traffic && <Delta n={o.traffic.delta_pct} unit="%" />}</div>
              {o.traffic ? (
                <><div className="text-2xl font-semibold leading-none tabular-nums tracking-[-0.01em]">{fmt(o.traffic.clicks_28d)}</div><Sparkline series={o.traffic.series} /><span className="text-[11px] text-mute">{__('From Google Search Console, through MonoRanks', 'monoranks')}</span></>
              ) : (
                <><div className="text-2xl font-semibold text-mute">—</div><span className="text-[11px] text-mute">{__('Connect Google Search Console in MonoRanks to see clicks here.', 'monoranks')}</span></>
              )}
            </Card>
            <Card className="flex flex-col gap-2.5 px-5 py-[18px]">
              <div className="flex items-center justify-between"><span className="text-[12px] font-medium text-ink2">{__('Fixes', 'monoranks')}</span>{o.fixes.ready > 0 && <Badge>{sprintf(__('%s ready', 'monoranks'), fmt(o.fixes.ready))}</Badge>}</div>
              <div className="text-2xl font-semibold leading-none tabular-nums tracking-[-0.01em]">{fmt(o.fixes.applied_30d)} <span className="text-[12px] font-normal text-ink2">{__('applied in 30 days', 'monoranks')}</span></div>
              <div className="flex flex-wrap gap-1.5">
                <Badge variant="pill"><Dot tone={o.ai.bots_rules ? 'good' : 'muted'} />{o.ai.bots_rules ? __('AI crawler rules on', 'monoranks') : __('No AI crawler rules', 'monoranks')}</Badge>
                <Badge variant="pill"><Dot tone={o.ai.llms_txt ? 'good' : 'muted'} />{o.ai.llms_txt ? __('llms.txt published', 'monoranks') : __('No llms.txt', 'monoranks')}</Badge>
              </div>
            </Card>
          </div>

          <div className="grid grid-cols-[minmax(0,3fr)_minmax(0,2fr)] items-start gap-5 max-[960px]:grid-cols-1">
            <Card>
              <CardHeader><CardTitle>{__('Pages needing attention', 'monoranks')}</CardTitle><Button variant="ghost" size="sm" asChild><a href={out(data.app_url, 'all-pages')} target="_blank" rel="noopener">{__('All pages in MonoRanks', 'monoranks')}</a></Button></CardHeader>
              {o.attention.length ? (
                <div className="overflow-x-auto rounded-b-xl pt-2">
                  <table className="w-full border-collapse">
                    <thead><tr><th className={th}>{__('Page', 'monoranks')}</th><th className={th}>{__('Health', 'monoranks')}</th><th className={th}>{__('AEO', 'monoranks')}</th><th className={th}>{__('Biggest issue', 'monoranks')}</th><th className={th}></th></tr></thead>
                    <tbody>
                      {o.attention.map((row) => (
                        <tr key={row.post_id + row.url} className="group hover:[&>td]:bg-surface2">
                          <td className={td}><div className="flex flex-col gap-0.5"><b className="font-semibold">{row.title || row.url}</b>{row.url && <code className="max-w-[180px] text-[11px]">{relativePath(row.url)}</code>}</div></td>
                          <td className={td}><Ring score={row.health} /></td>
                          <td className={td}><Ring score={row.aeo} /></td>
                          <td className={`${td} text-[12px]`}>{row.issue}</td>
                          <td className={`${td} text-end`}>{row.page_url && <Button size="sm" asChild><a href={out(row.page_url, 'fix-page')} target="_blank" rel="noopener">{__('Fix in MonoRanks', 'monoranks')}</a></Button>}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : <Empty title={__('Nothing needs attention', 'monoranks')} text={__('Every scored page is above 60. The next audit may change that.', 'monoranks')} />}
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>{__('Ready to apply', 'monoranks')}{o.ready.length > 0 && <Badge>{fmt(o.ready.length)}</Badge>}</CardTitle>
                {o.ready.length > 1 && <Button variant="primary" size="sm" disabled={busy !== null} aria-busy={busy === 'all'} onClick={() => run('all', () => api.apply('all'))}>{__('Apply all', 'monoranks')}</Button>}
              </CardHeader>
              <CardBody className="flex flex-col pt-1.5">
                {o.ready.length === 0 && <Empty title={__('No fixes waiting', 'monoranks')} text={__('Fixes you approve in MonoRanks appear here, ready to write into WordPress.', 'monoranks')} />}
                {o.ready.map((fix) => (
                  <div key={fix.id} className="flex items-start gap-3 border-b border-grid py-3 last-of-type:border-b-0">
                    <div className="grid min-w-0 flex-1 gap-1">
                      <div className="flex min-w-0 flex-wrap items-center gap-2">
                        <Badge>{data.labels[fix.field] ?? fix.field}</Badge>
                        <b className="min-w-0 truncate text-[12px] font-semibold"><bdi>{fix.title || (fix.field === 'redirect' ? fix.from : __('Site', 'monoranks'))}</bdi></b>
                      </div>
                      {fix.field === 'content' ? (
                        <span className="text-[12px] text-mute">{__('Adds one answer-first paragraph at the top. Review the before/after in MonoRanks first.', 'monoranks')}</span>
                      ) : (
                        <>
                          {fix.before && <s className="truncate text-[12px] text-mute"><bdi>{excerpt(fix.before, 90)}</bdi></s>}
                          <span className="text-[12px]"><bdi>{excerpt(fix.after, 120)}</bdi></span>
                        </>
                      )}
                    </div>
                    {fix.field === 'content' && fix.page_url ? (
                      <Button size="sm" asChild><a href={out(fix.page_url, 'review-fix')} target="_blank" rel="noopener">{__('Review', 'monoranks')}</a></Button>
                    ) : (
                      <Button size="sm" disabled={busy !== null} aria-busy={busy === fix.id} onClick={() => run(fix.id, () => api.apply(fix.id))}>{__('Apply', 'monoranks')}</Button>
                    )}
                  </div>
                ))}
                {o.ready.length > 0 && <div className="pt-3 text-[11px] text-mute">{__('Approved in MonoRanks. Each write is logged under Settings and can be undone for 30 days.', 'monoranks')}</div>}
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardHeader><CardTitle>{__('Recent changes', 'monoranks')}</CardTitle><Button variant="ghost" size="sm" asChild><a href={s.urls.settings} onClick={(e) => { e.preventDefault(); go('settings'); }}>{__('All changes', 'monoranks')}</a></Button></CardHeader>
            <div className="pt-2"><ChangeLog rows={data.log} busy={undoing} onUndo={(row) => { setUndoing(row.index); run('undo', () => api.undo(row, 'overview')); }} /></div>
          </Card>
        </>
      )}
    </Shell>
  );
}

function Skeleton() {
  return <div className="grid grid-cols-4 gap-[14px] max-[960px]:grid-cols-2 max-[600px]:grid-cols-1">{[0, 1, 2, 3].map((i) => <Card key={i} className="h-[150px] animate-pulse bg-surface2" />)}</div>;
}

export function ConnectSteps({ revoked, go }: { revoked: boolean; go: (next: Screen) => void }) {
  const s = adminSettings();
  return (
    <Card>
      <CardHeader><CardTitle>{revoked ? __('Connect MonoRanks again', 'monoranks') : __('Connect MonoRanks', 'monoranks')}</CardTitle><Badge variant="pill"><Dot tone={revoked ? 'critical' : 'muted'} />{revoked ? __('Key revoked', 'monoranks') : __('Not connected', 'monoranks')}</Badge></CardHeader>
      <CardBody className="flex flex-col gap-4">
        <Step n={1}><b className="text-[13px] font-semibold text-ink">{__('Connect from MonoRanks', 'monoranks')}</b><span className="text-[12px] text-ink2">{__('In MonoRanks open your website → Integrations → WordPress → Connect to WordPress, and allow MonoRanks when WordPress asks. Nothing is sent before you connect.', 'monoranks')}</span><div><Button variant="primary" size="sm" asChild><a href={out(s.urls.app, 'connect-step')} target="_blank" rel="noopener">{__('Open MonoRanks', 'monoranks')}</a></Button></div></Step>
        <Step n={2} later><b className="text-[13px] font-semibold text-ink">{__('Or paste a connector key', 'monoranks')}</b><span className="text-[12px] text-ink2">{__('Use this when your host blocks Application Passwords.', 'monoranks')}</span><div><Button size="sm" asChild><a href={s.urls.settings} onClick={(e) => { e.preventDefault(); go('settings'); }}>{__('Open settings', 'monoranks')}</a></Button></div></Step>
        <Step n={3} later><b className="text-[13px] font-semibold text-ink">{__('See scores here and in your Posts list', 'monoranks')}</b><span className="text-[12px] text-ink2">{__("After the first audit this page shows the site's health and AEO scores, pages needing attention and the fixes you approved; every post and page gets its scores in the list.", 'monoranks')}</span></Step>
      </CardBody>
    </Card>
  );
}

export function Step({ n, later, children }: { n: number; later?: boolean; children: React.ReactNode }) {
  return <div className="flex items-start gap-3"><span className={`flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full text-[12px] font-semibold ${later ? 'bg-surface3 text-ink2' : 'bg-ink text-page'}`}>{n}</span><div className="flex min-w-0 flex-1 flex-col gap-1">{children}</div></div>;
}

function Waiting({ data }: { data: OverviewData }) {
  const kind = data.status;
  const results = kind === 'unsupported'
    ? __('This MonoRanks does not share audit results with the plugin yet. Scores stay in MonoRanks for now; the plugin asks again tomorrow.', 'monoranks')
    : kind === 'error'
      ? __('MonoRanks could not be reached for the audit results. The plugin tries again within the hour, or when you reload this screen.', 'monoranks')
      : __('MonoRanks has not scored this site yet. Scores appear here and in the Posts list after the first weekly audit.', 'monoranks');
  return (
    <Card>
      <CardHeader><CardTitle>{__('Waiting for the first audit', 'monoranks')}</CardTitle><Badge variant="pill"><Dot tone="good" />{__('Connected', 'monoranks')}</Badge></CardHeader>
      <CardBody className="flex flex-col gap-4">
        <KV rows={[
          [__('Content', 'monoranks'), data.last_sent ? sprintf(__('%1$s published items sent %2$s', 'monoranks'), fmt(data.items), data.last_sent) : __('Not sent yet. Use "Sync content now" above.', 'monoranks')],
          [__('Audit results', 'monoranks'), <>{results}{data.fetched && <span className="text-mute"> · {sprintf(__('checked %s', 'monoranks'), data.fetched)}</span>}</>],
        ]} />
        <div><Button size="sm" asChild><a href={out(data.app_url, 'open-audit')} target="_blank" rel="noopener">{__('Open the audit in MonoRanks', 'monoranks')}</a></Button></div>
      </CardBody>
    </Card>
  );
}
