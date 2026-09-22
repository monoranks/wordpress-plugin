import { useEffect, useRef, useState } from 'react';
import { adminSettings } from '@/settings';

export type Screen = 'overview' | 'settings';

const PAGES: Record<Screen, string> = { overview: 'monoranks', settings: 'monoranks-settings' };

function fromUrl(): Screen {
  return new URLSearchParams(window.location.search).get('page') === PAGES.settings ? 'settings' : 'overview';
}

/**
 * Which screen is open. Overview and Settings are two WordPress menu entries (two URLs) but one app: switching between
 * them pushes the other URL without a page load and keeps the admin menu's highlight in step. Back and forward work.
 */
export function useScreen(): [Screen, (next: Screen) => void] {
  const [screen, setScreen] = useState<Screen>(fromUrl);
  const goRef = useRef<(next: Screen) => void>(() => undefined);
  useEffect(() => {
    const onPop = () => setScreen(fromUrl());
    window.addEventListener('popstate', onPop);
    // WordPress's own menu entries for the plugin (left sidebar) switch screens the same way instead of reloading.
    const onMenuClick = (e: MouseEvent) => {
      const a = (e.target as HTMLElement).closest<HTMLAnchorElement>('#toplevel_page_monoranks a[href*="page=monoranks"]');
      if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      const page = new URL(a.href, window.location.href).searchParams.get('page');
      const next: Screen | null = page === PAGES.settings ? 'settings' : page === PAGES.overview ? 'overview' : null;
      if (!next) return;
      e.preventDefault();
      goRef.current(next);
    };
    document.addEventListener('click', onMenuClick);
    return () => { window.removeEventListener('popstate', onPop); document.removeEventListener('click', onMenuClick); };
  }, []);
  useEffect(() => {
    highlightMenu(screen);
    document.title = document.title.replace(/^[^‹]*‹/, (adminSettings().titles[screen] || 'MonoRanks') + ' ‹');
  }, [screen]);
  const go = (next: Screen) => {
    if (next === screen) return;
    const url = next === 'settings' ? adminSettings().urls.settings : adminSettings().urls.overview;
    window.history.pushState({ monoranks: next }, '', url);
    setScreen(next);
    window.scrollTo({ top: 0 });
  };
  goRef.current = go;
  return [screen, go];
}

/** WordPress marks the current submenu item server-side; move the mark when the screen changes client-side. */
function highlightMenu(screen: Screen) {
  const menu = document.getElementById('toplevel_page_monoranks');
  if (!menu) return;
  menu.querySelectorAll<HTMLAnchorElement>('.wp-submenu a').forEach((a) => {
    const on = new URL(a.href, window.location.href).searchParams.get('page') === PAGES[screen];
    a.classList.toggle('current', on);
    a.parentElement?.classList.toggle('current', on);
    if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
  });
}
