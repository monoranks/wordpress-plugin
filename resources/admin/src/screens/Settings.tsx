import { useEffect, useState, type FormEvent } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ChevronRight } from 'lucide-react';
import { adminSettings, type Notice } from '@/settings';
import { api, errorText, type SettingsData } from '@/lib/api';
import { Shell, NoticeBox, KV, Logo } from '@/shell/Shell';
import { ChangeLog } from '@/shell/ChangeLog';
import { Step } from '@/screens/Overview';
import { Card, CardHeader, CardTitle, CardBody } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge, Dot } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Tip } from '@/components/ui/tip';
import { out } from '@/lib/links';
import type { Screen } from '@/lib/screen';
import { fmt } from '@/lib/format';

/** MonoRanks → Settings: connection state, the connector key, last sync, what is sent, recent changes, disconnect. */
export function Settings({ go }: { go: (next: Screen) => void }) {
  const s = adminSettings();
  const [data, setData] = useState<SettingsData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<Notice | null>(s.notice);
  const [busy, setBusy] = useState<string | null>(null);
  const [undoing, setUndoing] = useState<number | null>(null);
  const [keyOpen, setKeyOpen] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState(false);

  useEffect(() => {
    api.settings().then((d) => { setData(d); setKeyOpen(d.revoked); }).catch((e) => setError(errorText(e, __('Could not load the settings.', 'monoranks'))));
  }, []);

  // Connect opens MonoRanks in this tab and comes back here. If the person opened it in another tab anyway (middle click),
  // check every few seconds while this tab is visible, so the page shows Connected by itself. Gives up after 10 minutes.
  const [waitingSince, setWaitingSince] = useState<number | null>(null);
  useEffect(() => {
    if (waitingSince === null || data?.connected) return;
    let stopped = false;
    const check = () => {
      if (stopped || document.visibilityState !== 'visible') return;
      if (Date.now() - waitingSince > 10 * 60 * 1000) { setWaitingSince(null); return; }
      api.settings().then((d) => { if (!stopped && d.connected) { setData(d); setKeyOpen(false); setWaitingSince(null); } }).catch(() => {});
    };
    const timer = window.setInterval(check, 4000);
    document.addEventListener('visibilitychange', check);
    return () => { stopped = true; window.clearInterval(timer); document.removeEventListener('visibilitychange', check); };
  }, [waitingSince, data?.connected]);

  async function run(key: string, call: () => Promise<{ ok: boolean; notice: Notice; data: unknown }>) {
    setBusy(key);
    try {
      const r = await call();
      setNotice(r.notice);
      setData(r.data as SettingsData);
      window.scrollTo({ top: 0 });
    } catch (e) {
      setNotice({ type: 'error', text: errorText(e, __('Something went wrong. Try again.', 'monoranks')) });
    } finally {
      setBusy(null);
      setUndoing(null);
    }
  }

  const connected = !!data?.connected;
  const revoked = !!data?.revoked;

  return (
    <Shell section="settings" go={go} title={__('Settings', 'monoranks')}>
      <NoticeBox notice={notice} />
      {error && <NoticeBox notice={{ type: 'error', text: error }} />}
      {!data && !error && <Card className="h-[260px] animate-pulse bg-surface2" />}
      {data && (
        <>
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3"><span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] bg-surface2 [&_svg]:h-5 [&_svg]:w-5" aria-hidden="true"><Logo which="mark" /></span><CardTitle>{__('Connection', 'monoranks')}</CardTitle></div>
              {connected
                ? <Tip align="end" text={revoked ? __('MonoRanks no longer accepts this key. Connect again from MonoRanks or paste a new key below.', 'monoranks') : __('Sending published content to MonoRanks. Nothing else leaves this site.', 'monoranks')}><Badge variant="pill"><Dot tone={revoked ? 'critical' : 'good'} />{revoked ? __('Key revoked', 'monoranks') : __('Connected', 'monoranks')}</Badge></Tip>
                : <Badge variant="pill"><Dot tone="muted" />{__('Not connected', 'monoranks')}</Badge>}
            </CardHeader>
            <CardBody className="flex flex-col gap-4">
              {connected ? (
                <>
                  <KV rows={[
                    [__('Connector key', 'monoranks'), <span className="flex flex-wrap items-center gap-2"><code>{data.key_hint}</code><Tip text={data.key_via} /></span>],
                    [__('Last sync', 'monoranks'), <span className="flex flex-wrap items-center gap-2">{data.last_sent_date ? <Tip text={data.last_sent_date}><span className="tabular-nums">{data.last_sent_short}</span></Tip> : <span className="tabular-nums">{data.last_sent_short}</span>}{data.sending && <Badge>{sprintf(__('sending, batch %1$s of %2$s', 'monoranks'), fmt(data.sending.page), fmt(Math.max(1, data.sending.pages)))}</Badge>}{data.items > 0 && <Badge>{sprintf(_n('%s published item', '%s published items', data.items, 'monoranks'), fmt(data.items))}</Badge>}{data.last_error && <Badge variant="warn">{data.last_error}</Badge>}</span>],
                    ...(data.next_audit ? [[__('Next audit', 'monoranks'), data.next_audit] as [string, React.ReactNode]] : []),
                    data.app_password?.exists
                      ? [__('Fixes written with', 'monoranks'), <span className="flex flex-wrap items-center gap-2">{__('App password', 'monoranks')}<Tip text={__('Fixes are written with the "MonoRanks" Application Password. Revoke it in your profile to stop writes.', 'monoranks')} /><a href={data.app_password.revoke_url || data.profile_url}>{__('Revoke', 'monoranks')}</a></span>]
                      : [__('Fixes', 'monoranks'), <span className="flex flex-wrap items-center gap-2">{__('Not set up', 'monoranks')}<Tip text={__('Approve MonoRanks in WordPress so it can write the fixes you accept.', 'monoranks')} />{data.app_password?.available !== false && <a href={out(data.allow_url || data.connect_url, 'allow-fixes', 'settings')} target="_self">{__('Allow', 'monoranks')}</a>}</span>],
                    [__('Works with', 'monoranks'), data.seo_plugin && !data.seo_supported
                      ? <span className="flex flex-wrap items-center gap-2"><Badge variant="warn">{sprintf(__('%s detected', 'monoranks'), data.seo_plugin)}</Badge><Tip text={sprintf(__('MonoRanks cannot write titles, descriptions, canonicals or noindex into %s yet, so those fixes are refused', 'monoranks'), data.seo_plugin)} /></span>
                      : data.seo_plugin
                      ? <span className="flex flex-wrap items-center gap-2"><Badge>{sprintf(__('%s detected', 'monoranks'), data.seo_plugin)}</Badge><Tip text={sprintf(__('titles and descriptions go into the fields %s reads', 'monoranks'), data.seo_plugin)} /></span>
                      : <span className="flex flex-wrap items-center gap-2"><Badge>{__('No SEO plugin', 'monoranks')}</Badge><Tip text={__('MonoRanks prints the approved title, description, canonical and noindex tags itself', 'monoranks')} /></span>],
                  ]} />
                  <div className="flex flex-wrap items-center gap-2">
                    <Button variant="primary" disabled={busy !== null} aria-busy={busy === 'sync'} onClick={() => run('sync', () => api.sync('settings'))}>{__('Send content now', 'monoranks')}</Button>
                    <Tip text={__('Disconnect stops sending. Fixes already applied stay.', 'monoranks')}><Button disabled={busy !== null} aria-busy={busy === 'disconnect'} onClick={() => run('disconnect', () => api.disconnect())}>{__('Disconnect', 'monoranks')}</Button></Tip>
                  </div>
                </>
              ) : (
                <>
                  <Step n={1}><b className="text-[13px] font-semibold text-ink">{__('Connect to MonoRanks', 'monoranks')}</b><span className="text-[12px] text-ink2">{__('Sign in and approve. Takes a minute.', 'monoranks')}</span><div><Button variant="primary" size="sm" asChild><a href={out(data.connect_url, 'connect-step', 'settings')} target="_self" onClick={() => setWaitingSince(Date.now())}>{__('Connect', 'monoranks')}</a></Button></div></Step>
                  <Step n={2} later><span className="inline-flex items-center gap-1.5"><b className="text-[13px] font-semibold text-ink">{__('Or paste a connector key', 'monoranks')}</b><Tip text={__('Use this when your host blocks Application Passwords.', 'monoranks')} /></span><KeyForm data={data} busy={busy} onSubmit={(key, base) => run('connect', () => api.connect(key, base))} /></Step>
                </>
              )}
            </CardBody>
          </Card>

          {connected && (
            <Card>
              <details open={keyOpen} onToggle={(e) => setKeyOpen(e.currentTarget.open)} className="group">
                <summary className="flex cursor-pointer list-none items-center gap-2 px-[22px] py-4 font-medium text-ink2 hover:text-ink [&::-webkit-details-marker]:hidden"><ChevronRight size={14} className="mr-flip transition-transform group-open:rotate-90" />{__('Use a different key', 'monoranks')}</summary>
                <div className="border-t border-grid px-[22px] pb-[22px] pt-4">
                  <KeyForm data={data} busy={busy} hint={__('In MonoRanks: Settings → API and MCP → New credential, content:write, this website.', 'monoranks')} onSubmit={(key, base) => run('connect', () => api.connect(key, base))} />
                </div>
              </details>
            </Card>
          )}

          <Card>
            <details className="group">
              <summary className="flex cursor-pointer list-none items-center gap-2 px-[22px] py-4 font-medium text-ink2 hover:text-ink [&::-webkit-details-marker]:hidden"><ChevronRight size={14} className="mr-flip transition-transform group-open:rotate-90" />{__('What is sent', 'monoranks')}</summary>
              <div className="border-t border-grid px-[22px] pb-[22px] pt-4">
              <KV rows={[
                [__('Sent', 'monoranks'), __('Published titles, a 40-word excerpt, dates, authors, categories, tags, SEO fields, image alt text and plugin status.', 'monoranks')],
                [__('Read back', 'monoranks'), __("This website's audit results: scores, issues and the fixes you approved, shown here and in the Posts list.", 'monoranks')],
                [__('Never sent', 'monoranks'), __('Drafts, private posts, comments, emails, passwords, plugin or theme settings.', 'monoranks')],
                [__('Can change', 'monoranks'), __('SEO title, meta description, canonical, noindex, image alt text, redirects, one opening paragraph, AI crawler rules, llms.txt. Only after you approve each one.', 'monoranks')],
              ]} />
              </div>
            </details>
          </Card>

          <Card>
            <CardHeader><CardTitle>{__('Recent changes', 'monoranks')}</CardTitle><span className="text-[12px] text-mute">{__('Last 50 · undo for 30 days', 'monoranks')}</span></CardHeader>
            <div className="pt-2"><ChangeLog rows={data.log} busy={undoing} onUndo={(row) => { setUndoing(row.index); run('undo', () => api.undo(row, 'settings')); }} /></div>
          </Card>

          {!connected && data.has_data && (
            <Card className="border-critical/40">
              <CardHeader><CardTitle className="text-critical">{__('Danger zone', 'monoranks')}<Tip text={__('Erases the connection, scores, redirects, AI files and change log. Written fixes stay.', 'monoranks')} /></CardTitle></CardHeader>
              <CardBody className="flex flex-col gap-3">
                {confirmDelete ? (
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-[12px] font-medium text-critical">{__('This cannot be undone. Delete everything?', 'monoranks')}</span>
                    <Button variant="dangerSolid" disabled={busy !== null} aria-busy={busy === 'delete'} onClick={() => { setConfirmDelete(false); run('delete', () => api.deleteEverything()); }}>{__('Yes, delete all data', 'monoranks')}</Button>
                    <Button variant="ghost" disabled={busy !== null} onClick={() => setConfirmDelete(false)}>{__('Cancel', 'monoranks')}</Button>
                  </div>
                ) : (
                  <div><Button variant="danger" onClick={() => setConfirmDelete(true)}>{__('Delete all MonoRanks data', 'monoranks')}</Button></div>
                )}
              </CardBody>
            </Card>
          )}
        </>
      )}
    </Shell>
  );
}

function KeyForm({ data, busy, hint, onSubmit }: { data: SettingsData; busy: string | null; hint?: string; onSubmit: (key: string, base?: string) => void }) {
  const [key, setKey] = useState('');
  const [base, setBase] = useState(data.api_base);
  function submit(e: FormEvent) {
    e.preventDefault();
    onSubmit(key, data.show_address ? base : undefined);
  }
  return (
    <form onSubmit={submit} className="flex flex-col gap-3 pt-1.5">
      <div className="flex flex-col gap-2"><span className="inline-flex items-center gap-1.5"><label htmlFor="monoranks-key" className="text-[12px] font-medium text-ink2">{__('Connector key', 'monoranks')}</label>{hint && <Tip align="start" text={hint} />}</span><Input id="monoranks-key" name="key" type="password" autoComplete="off" placeholder="mr_ws_… or mr_site_…" required value={key} onChange={(e) => setKey(e.target.value)} /></div>
      {data.show_address && <div className="flex flex-col gap-2"><label htmlFor="monoranks-address" className="text-[12px] font-medium text-ink2">{__('MonoRanks address (development sites only)', 'monoranks')}</label><Input id="monoranks-address" name="api_base" type="url" value={base} onChange={(e) => setBase(e.target.value)} /></div>}
      <div><Button variant="primary" type="submit" disabled={busy !== null} aria-busy={busy === 'connect'}>{__('Save key', 'monoranks')}</Button></div>
    </form>
  );
}
