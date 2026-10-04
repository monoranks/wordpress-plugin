import { useEffect, useState, type ReactNode } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { api, type GrowData, type GrowState } from '@/lib/api';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { out } from '@/lib/links';
import { fmt } from '@/lib/format';

const ext = { target: '_blank', rel: 'noopener' } as const;

/**
 * "What to do next" on the Overview: outreach targets to contact, backlinks won and lost, competitors and the keywords
 * they win, keyword movers, and the newest report (src/Grow.php, REST /admin/grow). Loads after the rest of the
 * screen, so a slow MonoRanks never holds up the scores. Each card says plainly why it is empty.
 */
export function GrowCards() {
  const [data, setData] = useState<GrowData | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    api.grow().then(setData).catch(() => setFailed(true));
  }, []);

  if (failed) return null;
  if (!data) {
    return (
      <section aria-label={__('What to do next', 'monoranks')} aria-busy="true" className="grid grid-cols-2 gap-5 max-[960px]:grid-cols-1">
        {[0, 1, 2, 3].map((i) => <Card key={i} className="h-[180px] animate-pulse bg-surface2" />)}
      </section>
    );
  }
  if (!data.connected) return null;
  const scope = data.scope_url ?? '';

  return (
    <section aria-labelledby="mr-grow-title" className="flex flex-col gap-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="mr-grow-title" className="text-[15px] font-semibold text-ink">{__('What to do next', 'monoranks')}</h2>
        {data.fetched && <span className="text-[11px] text-mute">{sprintf(__('Updated %s', 'monoranks'), data.fetched)}</span>}
      </div>
      <div className="grid grid-cols-2 items-start gap-5 max-[960px]:grid-cols-1">
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
    text = <>{__('The MonoRanks key of this site cannot read this yet. Allow it under Integrations in MonoRanks, or connect again.', 'monoranks')} {scope && <a className="font-medium text-brand-ink hover:underline" href={out(scope, 'grow-scope')} {...ext}>{__('Open Integrations', 'monoranks')}</a>}</>;
  } else if (state === 'off') {
    text = __('Not available in your MonoRanks yet.', 'monoranks');
  } else if (state === 'error') {
    text = __('MonoRanks could not be reached. The plugin tries again within the hour.', 'monoranks');
  } else if (state === 'pending') {
    text = __('Still loading. Reload this screen in a minute.', 'monoranks');
  }
  return <p className="m-0 text-[12px] text-ink2">{text}</p>;
}

function GrowCard({ title, badge, link, linkText, children }: { title: string; badge?: ReactNode; link?: string; linkText: string; children: ReactNode }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}{badge}</CardTitle>
        {link && <Button variant="ghost" size="sm" asChild><a href={out(link, 'grow-card')} {...ext}>{linkText}<span className="sr-only"> {__('(opens in a new tab)', 'monoranks')}</span></a></Button>}
      </CardHeader>
      <CardBody className="flex flex-col gap-3 pt-3">{children}</CardBody>
    </Card>
  );
}

function Outreach({ d, scope }: { d: GrowData['outreach']; scope: string }) {
  const ok = d?.state === 'ok' ? d : null;
  return (
    <GrowCard title={__('Outreach', 'monoranks')} badge={ok && ok.to_contact > 0 ? <Badge>{sprintf(_n('%s to contact', '%s to contact', ok.to_contact, 'monoranks'), fmt(ok.to_contact))}</Badge> : undefined} link={ok?.link || d?.link} linkText={__('All targets', 'monoranks')}>
      {ok ? (
        <>
          {ok.top.length > 0 ? (
            <ol className="m-0 flex list-none flex-col gap-2.5 p-0">
              {ok.top.map((t) => (
                <li key={t.url} className="grid gap-0.5">
                  <a className="truncate text-[13px] font-semibold text-ink hover:underline" href={t.url} {...ext}><bdi>{t.title}</bdi><span className="sr-only"> {__('(opens in a new tab)', 'monoranks')}</span></a>
                  <span className="text-[12px] text-ink2">{t.why}{t.domain && <> · <bdi className="text-mute">{t.domain}</bdi></>}</span>
                </li>
              ))}
            </ol>
          ) : <StateLine state="empty" scope={scope} empty={__('Every target is contacted. MonoRanks looks for new ones each week.', 'monoranks')} />}
          <p className="m-0 text-[11px] text-mute">{__('Sites worth asking for a link or a mention. MonoRanks never sends email; you reach out and set the status there.', 'monoranks')}</p>
        </>
      ) : <StateLine state={d?.state ?? 'pending'} scope={scope} empty={__('No outreach targets yet. MonoRanks looks for pages worth a link each week.', 'monoranks')} />}
    </GrowCard>
  );
}

function Backlinks({ d, scope }: { d: GrowData['backlinks']; scope: string }) {
  const ok = d?.state === 'ok' ? d : null;
  return (
    <GrowCard title={__('Backlinks', 'monoranks')} link={ok?.link || d?.link} linkText={__('All backlinks', 'monoranks')}>
      {ok ? (
        <>
          <div className="flex flex-wrap items-baseline gap-x-5 gap-y-1">
            <span className="text-2xl font-semibold leading-none tabular-nums text-good-ink">+{fmt(ok.new)}</span>
            <span className="text-2xl font-semibold leading-none tabular-nums text-critical">−{fmt(ok.lost)}</span>
            <span className="text-[12px] text-ink2">{__('websites linking to you, won and lost in 28 days', 'monoranks')}</span>
          </div>
          <div className="flex flex-wrap gap-1.5">
            <Badge variant="pill">{sprintf(_n('%s website links to you', '%s websites link to you', ok.domains, 'monoranks'), fmt(ok.domains))}</Badge>
            {ok.review > 0 && <Badge variant="pill">{sprintf(_n('%s to review for spam', '%s to review for spam', ok.review, 'monoranks'), fmt(ok.review))}</Badge>}
          </div>
          {ok.top.length > 0 && (
            <div className="grid gap-1">
              <span className="text-[12px] text-mute">{__('Newest strong links', 'monoranks')}</span>
              <ul className="m-0 flex list-none flex-wrap gap-x-3 gap-y-1 p-0 text-[12px]">
                {ok.top.map((b) => <li key={b.domain}><bdi>{b.domain}</bdi>{b.rank !== null && <span className="text-mute"> · {sprintf(__('strength %s', 'monoranks'), fmt(b.rank))}</span>}</li>)}
              </ul>
            </div>
          )}
        </>
      ) : <StateLine state={d?.state ?? 'pending'} scope={scope} empty={__('No backlink data yet. MonoRanks refreshes it weekly.', 'monoranks')} />}
    </GrowCard>
  );
}

function Competitors({ d, scope }: { d: GrowData['competitors']; scope: string }) {
  const ok = d?.state === 'ok' ? d : null;
  return (
    <GrowCard title={__('Competitors', 'monoranks')} badge={ok && ok.gaps ? <Badge>{sprintf(_n('%s keyword gap', '%s keyword gaps', ok.gaps, 'monoranks'), fmt(ok.gaps))}</Badge> : undefined} link={ok?.link || d?.link} linkText={__('All competitors', 'monoranks')}>
      {ok ? (
        <>
          <ul className="m-0 flex list-none flex-col gap-2 p-0">
            {ok.top.map((c) => (
              <li key={c.domain} className="grid gap-0.5">
                <span className="text-[13px] font-semibold"><bdi>{c.domain}</bdi>{!c.picked && <span className="ms-1.5 text-[11px] font-normal text-mute">{__('suggested', 'monoranks')}</span>}</span>
                <span className="text-[12px] text-ink2">{c.reason || sprintf(_n('%1$s shared keyword, %2$s where they rank higher', '%1$s shared keywords, %2$s where they rank higher', c.shared, 'monoranks'), fmt(c.shared), fmt(c.above_us))}</span>
              </li>
            ))}
          </ul>
          {ok.keywords.length > 0 && (
            <div className="grid gap-1">
              <span className="text-[12px] text-mute">{__('They rank for these, you do not', 'monoranks')}</span>
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
    <li className="flex items-baseline justify-between gap-3 text-[12px]">
      <bdi className="min-w-0 truncate">{k.keyword}</bdi>
      <span className={`shrink-0 tabular-nums ${up ? 'text-good-ink' : 'text-critical'}`}>
        <span className="sr-only">{up ? __('up', 'monoranks') : __('down', 'monoranks')} </span>
        {sprintf(__('%1$s → %2$s', 'monoranks'), fmt(k.from), fmt(k.to))}
      </span>
    </li>
  );
  return (
    <GrowCard title={__('Keyword movers this week', 'monoranks')} link={ok?.link || d?.link} linkText={__('All keywords', 'monoranks')}>
      {ok ? (
        ok.up.length || ok.down.length ? (
          <div className="grid grid-cols-2 gap-4 max-[600px]:grid-cols-1">
            <div className="grid content-start gap-1">
              <span className="text-[12px] font-medium text-ink2">{__('Up', 'monoranks')}</span>
              {ok.up.length ? <ul className="m-0 flex list-none flex-col gap-1 p-0">{ok.up.map((k) => <Move key={k.keyword} k={k} up />)}</ul> : <span className="text-[12px] text-mute">—</span>}
            </div>
            <div className="grid content-start gap-1">
              <span className="text-[12px] font-medium text-ink2">{__('Down', 'monoranks')}</span>
              {ok.down.length ? <ul className="m-0 flex list-none flex-col gap-1 p-0">{ok.down.map((k) => <Move key={k.keyword} k={k} up={false} />)}</ul> : <span className="text-[12px] text-mute">—</span>}
            </div>
          </div>
        ) : <p className="m-0 text-[12px] text-ink2">{sprintf(_n('Your %s tracked keyword held its place this week.', 'Your %s tracked keywords held their places this week.', ok.tracked, 'monoranks'), fmt(ok.tracked))}</p>
      ) : <StateLine state={d?.state ?? 'pending'} scope={scope} empty={__('No tracked keywords yet. Track some in MonoRanks to see them move here.', 'monoranks')} />}
    </GrowCard>
  );
}

function Report({ d }: { d: GrowData['report'] }) {
  if (!d || (d.state !== 'ok' && d.state !== 'empty')) return null;
  return (
    <Card className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
      {d.state === 'ok' ? (
        <>
          <span className="text-[13px]"><b className="font-semibold">{d.client ? __('Latest client report', 'monoranks') : __('Latest report', 'monoranks')}</b> · <bdi>{d.scope || d.title}</bdi>{d.when && <span className="text-mute"> · {d.when}</span>}</span>
          {d.link && <Button size="sm" asChild><a href={out(d.link, 'latest-report')} {...ext}>{__('Open report', 'monoranks')}<span className="sr-only"> {__('(opens in a new tab)', 'monoranks')}</span></a></Button>}
        </>
      ) : (
        <>
          <span className="text-[13px] text-ink2">{__('No reports yet. Create a website or client report in MonoRanks to share progress.', 'monoranks')}</span>
          {d.all_link && <Button size="sm" variant="ghost" asChild><a href={out(d.all_link, 'reports')} {...ext}>{__('Open reports', 'monoranks')}</a></Button>}
        </>
      )}
    </Card>
  );
}
