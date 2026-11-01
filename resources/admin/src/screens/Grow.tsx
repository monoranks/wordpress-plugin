import { useEffect, useState, type ReactNode } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api, type GrowData, type GrowState } from '@/lib/api';
import { ArrowUpRight } from 'lucide-react';
import { Card, CardHeader, CardTitle, CardBody, Tile, TileLabel } from '@/components/ui/card';
import { Tip } from '@/components/ui/tip';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { out } from '@/lib/links';
import { fmt } from '@/lib/format';

const ext = { target: '_blank', rel: 'noopener' } as const;
const SECTIONS = ['outreach', 'backlinks', 'competitors', 'keywords', 'report'] as const;

/**
 * "What to do next" on the Overview: outreach targets to contact, backlinks won and lost, competitors and the keywords
 * they win, keyword movers, and the newest report (src/Grow.php, REST /admin/grow). Loads after the rest of the
 * screen, so a slow MonoRanks never holds up the scores. Each card says plainly why it is empty.
 */
export function GrowCards() {
  const [data, setData] = useState<GrowData | null>(null);
  const [failed, setFailed] = useState(false);

  // A card still "checking" (the first answer was slow, or MonoRanks finishes it in the background) is asked again a few
  // times, a minute apart, so it fills in without a reload. The plugin itself decides when MonoRanks is really called.
  const [tries, setTries] = useState(0);
  useEffect(() => {
    api.grow().then(setData).catch(() => setFailed(true));
  }, [tries]);
  const checking = !!data?.connected && SECTIONS.some((k) => data[k]?.state === 'pending');
  useEffect(() => {
    if (!checking || tries >= 4) return;
    const timer = window.setTimeout(() => setTries((n) => n + 1), 60 * 1000);
    return () => window.clearTimeout(timer);
  }, [checking, tries]);

  if (failed) return null;
  if (!data) {
    return (
      <section aria-label={__('What to do next', 'monoranks')} aria-busy="true" className="grid grid-cols-2 gap-6 max-[960px]:grid-cols-1">
        {[0, 1, 2, 3].map((i) => <Card key={i} className="h-[180px] animate-pulse bg-surface2" />)}
      </section>
    );
  }
  if (!data.connected) return null;
  const scope = data.scope_url ?? '';

  return (
    <section aria-labelledby="mr-grow-title" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <h2 id="mr-grow-title" className="text-[20px] font-bold tracking-[-0.01em] text-ink">{__('What to do next', 'monoranks')}</h2>
        {data.fetched && <Tip text={sprintf(__('Updated %s', 'monoranks'), data.fetched)} />}
      </div>
      <div className="grid grid-cols-2 items-stretch gap-6 max-[960px]:grid-cols-1">
        <Outreach d={data.outreach} scope={scope} />
        <Backlinks d={data.backlinks} scope={scope} />
        <Competitors d={data.competitors} scope={scope} />
        <Keywords d={data.keywords} scope={scope} />
      </div>
      <Report d={data.report} />
    </section>
  );
}

/** The line a card shows when it has nothing to list. */
function StateLine({ state, scope, empty }: { state: GrowState; scope: string; empty: string }) {
  let text: ReactNode = empty;
  if (state === 'no_access') {
    text = <span className="inline-flex flex-wrap items-center gap-1.5">{__('No access yet.', 'monoranks')}<Tip text={__('Allow it under Integrations in MonoRanks, or connect again.', 'monoranks')} />{scope && <a className="font-semibold text-brand-ink hover:underline" href={out(scope, 'grow-scope')} {...ext}>{__('Allow', 'monoranks')}</a>}</span>;
  } else if (state === 'off') {
    text = __('Not available yet.', 'monoranks');
  } else if (state === 'error') {
    text = __('Could not reach MonoRanks. Retrying soon.', 'monoranks');
  } else if (state === 'pending') {
    return <p className="m-0 animate-pulse text-[14px] text-mute" role="status">{__('Checking…', 'monoranks')}</p>;
  }
  return <p className="m-0 text-[14px] text-ink2">{text}</p>;
}

function GrowCard({ title, badge, link, linkText, children }: { title: string; badge?: ReactNode; link?: string; linkText: string; children: ReactNode }) {
  return (
    <Card className="h-full">
      <CardHeader>
        <CardTitle>{title}{badge}</CardTitle>
        {link && <Button variant="ghost" size="sm" asChild><a href={out(link, 'grow-card')} {...ext}>{linkText}<ArrowUpRight size={14} className="mr-flip" /><span className="sr-only"> {__('(opens in a new tab)', 'monoranks')}</span></a></Button>}
      </CardHeader>
      <CardBody className="flex flex-col gap-4 pt-4">{children}</CardBody>
    </Card>
  );
}

function Outreach({ d, scope }: { d: GrowData['outreach']; scope: string }) {
  const ok = d?.state === 'ok' ? d : null;
  return (
    <GrowCard title={__('Ask for links', 'monoranks')} badge={ok && ok.to_contact > 0 ? <Badge>{sprintf(_n('%s to contact', '%s to contact', ok.to_contact, 'monoranks'), fmt(ok.to_contact))}</Badge> : undefined} link={ok?.link || d?.link} linkText={__('All', 'monoranks')}>
      {ok ? (
        <>
          {ok.top.length > 0 ? (
            <ol className="m-0 flex list-none flex-col p-0">
              {ok.top.map((t) => (
                <li key={t.url} className="flex min-h-[48px] min-w-0 items-center gap-2 border-b border-grid py-2 last:border-b-0">
                  <a className="min-w-0 truncate text-[14px] font-semibold text-ink hover:text-brand-ink" href={t.url} {...ext}><bdi>{t.title}</bdi><span className="sr-only"> {__('(opens in a new tab)', 'monoranks')}</span></a>
                  {t.why && <Tip text={t.why} />}
                  {t.domain && <bdi className="ms-auto shrink-0 text-[13px] text-mute">{t.domain}</bdi>}
                </li>
              ))}
            </ol>
          ) : <StateLine state="empty" scope={scope} empty={__('Every target is contacted. MonoRanks looks for new ones each week.', 'monoranks')} />}
        </>
      ) : <StateLine state={d?.state ?? 'pending'} scope={scope} empty={__('No outreach targets yet. MonoRanks looks for pages worth a link each week.', 'monoranks')} />}
    </GrowCard>
  );
}

function Backlinks({ d, scope }: { d: GrowData['backlinks']; scope: string }) {
  const ok = d?.state === 'ok' ? d : null;
  return (
    <GrowCard title={__('Backlinks', 'monoranks')} link={ok?.link || d?.link} linkText={__('All', 'monoranks')}>
      {ok ? (
        <>
          <div className="grid grid-cols-3 gap-2.5 max-[600px]:grid-cols-1">
            <Tile><TileLabel>{__('Won', 'monoranks')}<Tip text={__('New websites linking to you, last 28 days.', 'monoranks')} /></TileLabel><span className="text-[32px] font-[750] leading-none tracking-[-0.03em] tabular-nums text-good-ink">+{fmt(ok.new)}</span></Tile>
            <Tile><TileLabel>{__('Lost', 'monoranks')}</TileLabel><span className="text-[32px] font-[750] leading-none tracking-[-0.03em] tabular-nums text-critical">−{fmt(ok.lost)}</span></Tile>
            <Tile><TileLabel>{__('Linking sites', 'monoranks')}</TileLabel><span className="text-[32px] font-[750] leading-none tracking-[-0.03em] tabular-nums">{fmt(ok.domains)}</span></Tile>
          </div>
          {ok.review > 0 && <div><Badge variant="warn">{sprintf(_n('%s to check for spam', '%s to check for spam', ok.review, 'monoranks'), fmt(ok.review))}</Badge></div>}
        </>
      ) : <StateLine state={d?.state ?? 'pending'} scope={scope} empty={__('No backlink data yet. MonoRanks refreshes it weekly.', 'monoranks')} />}
    </GrowCard>
  );
}

function Competitors({ d, scope }: { d: GrowData['competitors']; scope: string }) {
  const ok = d?.state === 'ok' ? d : null;
  return (
    <GrowCard title={__('Competitors', 'monoranks')} badge={ok && ok.gaps ? <Badge>{sprintf(_n('%s gap', '%s gaps', ok.gaps, 'monoranks'), fmt(ok.gaps))}</Badge> : undefined} link={ok?.link || d?.link} linkText={__('All', 'monoranks')}>
      {ok ? (
        <>
          <ul className="m-0 flex list-none flex-col p-0">
            {ok.top.map((c) => (
              <li key={c.domain} className="flex min-h-[48px] min-w-0 items-center gap-2 border-b border-grid py-2 last:border-b-0">
                <span className="min-w-0 truncate text-[14px] font-semibold"><bdi>{c.domain}</bdi></span>
                {!c.picked && <Badge>{__('Suggested', 'monoranks')}</Badge>}
                <Tip text={c.reason || sprintf(_n('%1$s shared keyword, %2$s where they rank higher', '%1$s shared keywords, %2$s where they rank higher', c.shared, 'monoranks'), fmt(c.shared), fmt(c.above_us))} />
                <span className="ms-auto shrink-0 text-[13px] text-mute">{sprintf(__('%s ahead of you', 'monoranks'), fmt(c.above_us))}</span>
              </li>
            ))}
          </ul>
          {ok.keywords.length > 0 && (
            <div className="grid gap-2">
              <TileLabel>{__('Words you miss', 'monoranks')}<Tip text={__('They rank for these, you do not', 'monoranks')} /></TileLabel>
              <ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
                {ok.keywords.map((k) => <li key={k.keyword}><Badge variant="pill"><bdi>{k.keyword}</bdi>{k.volume !== null && <span className="tabular-nums text-ink2">{sprintf(__('%s/mo', 'monoranks'), fmt(k.volume))}</span>}</Badge></li>)}
              </ul>
            </div>
          )}
        </>
      ) : <StateLine state={d?.state ?? 'pending'} scope={scope} empty={__('No competitors yet. Pick some in MonoRanks to see the keywords they win.', 'monoranks')} />}
    </GrowCard>
  );
}

function Keywords({ d, scope }: { d: GrowData['keywords']; scope: string }) {
  const ok = d?.state === 'ok' ? d : null;
  const Move = ({ k, up }: { k: { keyword: string; from: number; to: number }; up: boolean }) => (
    <li className="flex min-h-[40px] items-center justify-between gap-3 border-b border-grid text-[14px] last:border-b-0">
      <bdi className="min-w-0 truncate font-semibold">{k.keyword}</bdi>
      <span className={`shrink-0 rounded-md px-2 py-0.5 text-[13px] font-bold tabular-nums ${up ? 'bg-[color-mix(in_srgb,var(--good)_12%,transparent)] text-good-ink' : 'bg-[color-mix(in_srgb,var(--critical)_12%,transparent)] text-critical'}`}>
        <span className="sr-only">{up ? __('up', 'monoranks') : __('down', 'monoranks')} </span>
        {sprintf(__('%1$s → %2$s', 'monoranks'), fmt(k.from), fmt(k.to))}
      </span>
    </li>
  );
  return (
    <GrowCard title={__('Keyword moves', 'monoranks')} link={ok?.link || d?.link} linkText={__('All', 'monoranks')}>
      {ok ? (
        ok.up.length || ok.down.length ? (
          <div className="grid grid-cols-2 gap-6 max-[600px]:grid-cols-1">
            <div className="grid content-start gap-1">
              <TileLabel>{__('Up', 'monoranks')}</TileLabel>
              {ok.up.length ? <ul className="m-0 flex list-none flex-col p-0">{ok.up.map((k) => <Move key={k.keyword} k={k} up />)}</ul> : <span className="text-[14px] text-mute">—</span>}
            </div>
            <div className="grid content-start gap-1">
              <TileLabel>{__('Down', 'monoranks')}</TileLabel>
              {ok.down.length ? <ul className="m-0 flex list-none flex-col p-0">{ok.down.map((k) => <Move key={k.keyword} k={k} up={false} />)}</ul> : <span className="text-[14px] text-mute">—</span>}
            </div>
          </div>
        ) : <p className="m-0 text-[14px] text-ink2">{sprintf(_n('Your %s tracked keyword held its place this week.', 'Your %s tracked keywords held their places this week.', ok.tracked, 'monoranks'), fmt(ok.tracked))}</p>
      ) : <StateLine state={d?.state ?? 'pending'} scope={scope} empty={__('No tracked keywords yet. Track some in MonoRanks to see them move here.', 'monoranks')} />}
    </GrowCard>
  );
}

function Report({ d }: { d: GrowData['report'] }) {
  if (!d || (d.state !== 'ok' && d.state !== 'empty')) return null;
  const emails = d.state === 'ok' ? d.emails ?? [] : [];
  const reports = d.state === 'ok' ? d.reports ?? [] : [];
  return (
    <Card className="grid gap-2 px-7 py-5 max-[782px]:px-[18px]">
      {d.state === 'empty' ? (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <span className="inline-flex items-center gap-1.5 text-[15px] font-semibold text-ink2">{__('No reports yet', 'monoranks')}<Tip text={__('Create a report or a weekly email in MonoRanks to share progress.', 'monoranks')} /></span>
          {d.all_link && <Button size="sm" variant="ghost" asChild><a href={out(d.all_link, 'reports')} {...ext}>{__('All', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a></Button>}
        </div>
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-[18px] font-bold tracking-[-0.01em]">{__('Reports', 'monoranks')}</h2>
            {d.all_link && <Button size="sm" variant="ghost" asChild><a href={out(d.all_link, 'reports')} {...ext}>{__('All', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /></a></Button>}
          </div>
          <ul className="m-0 flex list-none flex-col p-0">
            {emails.map((m, i) => (
              <li key={`e${i}`} className="flex min-h-[52px] flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-grid py-2 last:border-b-0">
                <span className="inline-flex items-center gap-2 text-[14px]"><b className="font-semibold"><bdi>{m.name}</bdi></b><Badge>{__('Weekly email', 'monoranks')}</Badge>{m.enabled && m.next_label ? <Tip text={sprintf(__('Next send %s', 'monoranks'), m.next_label)} /> : null}</span>
                <span className="flex flex-wrap items-center gap-2 text-[13px] text-ink2">
                  <span>{sprintf(_n('%s recipient', '%s recipients', m.recipients, 'monoranks'), fmt(m.recipients))}</span>
                  <Badge variant="pill"><span className={`inline-block h-2 w-2 rounded-full ${m.enabled ? 'bg-good' : 'bg-mute'}`} />{m.enabled ? __('On', 'monoranks') : __('Off', 'monoranks')}</Badge>
                </span>
              </li>
            ))}
            {reports.map((r, i) => (
              <li key={`r${i}`} className="flex min-h-[52px] flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-grid py-2 last:border-b-0">
                <span className="inline-flex items-center gap-2 text-[14px]"><b className="font-semibold"><bdi>{r.scope || r.title}</bdi></b><Badge>{r.client ? __('Client report', 'monoranks') : __('Report', 'monoranks')}</Badge>{r.when && <Tip text={r.when} />}</span>
                {r.link && <Button size="sm" asChild><a href={out(r.link, 'latest-report')} {...ext}>{__('Open', 'monoranks')}<ArrowUpRight size={14} className="mr-flip" /><span className="sr-only"> {__('(opens in a new tab)', 'monoranks')}</span></a></Button>}
              </li>
            ))}
          </ul>
        </>
      )}
    </Card>
  );
}
