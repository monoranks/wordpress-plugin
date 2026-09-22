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
import { out } from '@/lib/links';
import type { Screen } from '@/lib/screen';

/** MonoRanks → Settings: connection state, the connector key, last sync, what is sent, recent changes, disconnect. */
export function Settings({ go }: { go: (next: Screen) => void }) {
  const s = adminSettings();
  const [data, setData] = useState<SettingsData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<Notice | null>(s.notice);
  const [busy, setBusy] = useState<string | null>(null);
  const [undoing, setUndoing] = useState<number | null>(null);
  const [keyOpen, setKeyOpen] = useState(false);

  useEffect(() => {
    api.settings().then((d) => { setData(d); setKeyOpen(d.revoked); }).catch((e) => setError(errorText(e, __('Could not load the settings.', 'monoranks'))));
  }, []);

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
    <Shell section="settings" go={go} title={__('Settings', 'monoranks')} description={__('Connection, what is sent, and every change written into this site.', 'monoranks')}>
      <NoticeBox notice={notice} />
      {error && <NoticeBox notice={{ type: 'error', text: error }} />}
      {!data && !error && <Card className="h-[260px] animate-pulse bg-surface2" />}
      {data && (
        <>
          <Card>
            <CardHeader>
              <div className="flex items-center gap-3"><span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] bg-surface2 [&_svg]:h-5 [&_svg]:w-5" aria-hidden="true"><Logo which="mark" /></span><CardTitle>{__('Connection', 'monoranks')}</CardTitle></div>
              <Badge variant="pill"><Dot tone={revoked ? 'critical' : connected ? 'good' : 'muted'} />{revoked ? __('Key revoked', 'monoranks') : connected ? __('Connected', 'monoranks') : __('Not connected', 'monoranks')}</Badge>
            </CardHeader>
            <CardBody className="flex flex-col gap-4">
              {connected ? (
                <>
                  <KV rows={[
                    [__('Status', 'monoranks'), revoked ? __('MonoRanks no longer accepts this key. Connect again from MonoRanks or paste a new key below.', 'monoranks') : __('Sending published content to MonoRanks. Nothing else leaves this site.', 'monoranks')],
                    [__('Connector key', 'monoranks'), <span className="flex flex-wrap items-center gap-2"><code>{data.key_hint}</code><span className="text-mute">{data.key_via}</span></span>],
                    [__('Last sync', 'monoranks'), <span className="flex flex-wrap items-center gap-2"><span className="tabular-nums">{data.last_sent}</span>{data.sending && <Badge>{sprintf(__('sending, batch %1$s of %2$s', 'monoranks'), String(data.sending.page), String(Math.max(1, data.sending.pages)))}</Badge>}{data.items > 0 && <Badge>{sprintf(_n('%s published item', '%s published items', data.items, 'monoranks'), String(data.items))}</Badge>}{data.last_error && <Badge variant="warn">{data.last_error}</Badge>}</span>],
                    ...(data.next_audit ? [[__('Next audit', 'monoranks'), data.next_audit] as [string, React.ReactNode]] : []),
                    [__('Fixes written with', 'monoranks'), <>{__('The "MonoRanks" Application Password', 'monoranks')} · <a href={data.profile_url}>{__('revoke it in your profile', 'monoranks')}</a> {__('to stop writes', 'monoranks')}</>],
                    [__('Works with', 'monoranks'), data.seo_plugin
                      ? <span className="flex flex-wrap items-center gap-2"><Badge>{sprintf(__('%s detected', 'monoranks'), data.seo_plugin)}</Badge><span className="text-mute">{sprintf(__('titles and descriptions go into the fields %s reads', 'monoranks'), data.seo_plugin)}</span></span>
                      : <span className="flex flex-wrap items-center gap-2"><Badge>{__('No SEO plugin', 'monoranks')}</Badge><span className="text-mute">{__('MonoRanks prints the approved title, description, canonical and noindex tags itself', 'monoranks')}</span></span>],
                  ]} />
                  <div className="flex flex-wrap items-center gap-2">
                    <Button variant="primary" disabled={busy !== null} aria-busy={busy === 'sync'} onClick={() => run('sync', () => api.sync('settings'))}>{__('Send content now', 'monoranks')}</Button>
                    <Button disabled={busy !== null} aria-busy={busy === 'disconnect'} onClick={() => run('disconnect', () => api.disconnect())}>{__('Disconnect', 'monoranks')}</Button>
                    <span className="text-[12px] text-mute">{__('Disconnect stops sending. Fixes already applied stay.', 'monoranks')}</span>
                  </div>
                </>
              ) : (
                <>
                  <Step n={1}><b className="text-[13px] font-semibold text-ink">{__('Connect from MonoRanks', 'monoranks')}</b><span className="text-[12px] text-ink2">{__('In MonoRanks open your website → Integrations → WordPress → Connect to WordPress, and allow MonoRanks when WordPress asks. Nothing is sent before you connect.', 'monoranks')}</span><div><Button variant="primary" size="sm" asChild><a href={out(data.app_url, 'connect-step', 'settings')} target="_blank" rel="noopener">{__('Open MonoRanks', 'monoranks')}</a></Button></div></Step>
                  <Step n={2} later><b className="text-[13px] font-semibold text-ink">{__('Or paste a connector key', 'monoranks')}</b><span className="text-[12px] text-ink2">{__('Use this when your host blocks Application Passwords. In MonoRanks: Settings → API and MCP → New credential with the content:write scope, ticked for this website.', 'monoranks')}</span><KeyForm data={data} busy={busy} onSubmit={(key, base) => run('connect', () => api.connect(key, base))} /></Step>
                </>
              )}
            </CardBody>
          </Card>

          {connected && (
            <Card>
              <CardBody className="pt-[18px]">
                <details open={keyOpen} onToggle={(e) => setKeyOpen(e.currentTarget.open)} className="group">
                  <summary className="inline-flex cursor-pointer list-none items-center gap-2 font-medium text-ink2 [&::-webkit-details-marker]:hidden"><ChevronRight size={14} className="mr-flip transition-transform group-open:rotate-90" />{__('Use a different key', 'monoranks')}</summary>
                  <div className="flex flex-col gap-[14px] pt-[14px]">
                    <div className="rounded-lg bg-surface2 px-[14px] py-3 text-[12px] text-ink2">{__('In MonoRanks: Settings → API and MCP → New credential with the content:write scope, ticked for this website. Paste it here. With a key alone MonoRanks receives your content; to apply fixes, also use Connect to WordPress in MonoRanks.', 'monoranks')}</div>
                    <KeyForm data={data} busy={busy} onSubmit={(key, base) => run('connect', () => api.connect(key, base))} />
                  </div>
                </details>
              </CardBody>
            </Card>
          )}

          <Card>
            <CardHeader><CardTitle>{__('What is sent', 'monoranks')}</CardTitle></CardHeader>
            <CardBody>
              <KV rows={[
                [__('Sent', 'monoranks'), __('Published titles, a 40-word excerpt, dates, authors, categories, tags, SEO fields, image alt text and plugin status.', 'monoranks')],
                [__('Read back', 'monoranks'), __("This website's audit results: scores, issues and the fixes you approved, shown here and in the Posts list.", 'monoranks')],
                [__('Never sent', 'monoranks'), __('Drafts, private posts, comments, emails, passwords, plugin or theme settings.', 'monoranks')],
                [__('Can change', 'monoranks'), __('SEO title, meta description, canonical, noindex, image alt text, redirects, one opening paragraph, AI crawler rules, llms.txt. Only after you approve each one.', 'monoranks')],
              ]} />
            </CardBody>
          </Card>

          <Card>
            <CardHeader><CardTitle>{__('Recent changes', 'monoranks')}</CardTitle><span className="text-[12px] text-mute">{__('Last 50 · undo for 30 days', 'monoranks')}</span></CardHeader>
            <div className="pt-2"><ChangeLog rows={data.log} busy={undoing} onUndo={(i) => { setUndoing(i); run('undo', () => api.undo(i, 'settings')); }} /></div>
          </Card>
        </>
      )}
    </Shell>
  );
}

function KeyForm({ data, busy, onSubmit }: { data: SettingsData; busy: string | null; onSubmit: (key: string, base?: string) => void }) {
  const [key, setKey] = useState('');
  const [base, setBase] = useState(data.api_base);
  function submit(e: FormEvent) {
    e.preventDefault();
    onSubmit(key, data.show_address ? base : undefined);
  }
  return (
    <form onSubmit={submit} className="flex flex-col gap-3 pt-1.5">
      <div className="flex flex-col gap-2"><label htmlFor="monoranks-key" className="text-[12px] font-medium text-ink2">{__('Connector key', 'monoranks')}</label><Input id="monoranks-key" name="key" type="password" autoComplete="off" placeholder="mr_ws_… or mr_site_…" required value={key} onChange={(e) => setKey(e.target.value)} /></div>
      {data.show_address && <div className="flex flex-col gap-2"><label htmlFor="monoranks-address" className="text-[12px] font-medium text-ink2">{__('MonoRanks address (development sites only)', 'monoranks')}</label><Input id="monoranks-address" name="api_base" type="url" value={base} onChange={(e) => setBase(e.target.value)} /></div>}
      <div><Button variant="primary" type="submit" disabled={busy !== null} aria-busy={busy === 'connect'}>{__('Connect', 'monoranks')}</Button></div>
    </form>
  );
}
