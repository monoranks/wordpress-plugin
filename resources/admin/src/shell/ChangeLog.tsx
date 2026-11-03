import { Fragment, useState, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { ChevronRight, Undo2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty } from '@/shell/Shell';
import { shortDate } from '@/shell/Ring';
import type { LogRow } from '@/lib/api';

/** Table cells in the app's redesign: quiet uppercase headers, tall rows, hairlines only. */
export const th = 'whitespace-nowrap border-b border-grid px-3.5 py-3 text-start text-[12px] font-bold uppercase tracking-[.06em] text-mute first:ps-7 last:pe-7 max-[782px]:first:ps-[18px] max-[782px]:last:pe-[18px]';
export const td = 'h-[60px] border-b border-grid px-3.5 py-3 align-middle text-[14px] first:ps-7 last:pe-7 group-last/row:border-b-0 max-[782px]:first:ps-[18px] max-[782px]:last:pe-[18px]';

/**
 * Recent changes written by MonoRanks, newest first: where, what and when on one line, with Undo. A click on the row
 * opens the rest (before, after, who).
 */
export function ChangeLog({ rows, onUndo, busy }: { rows: LogRow[]; onUndo: (row: LogRow) => void; busy: number | null }) {
  const [open, setOpen] = useState<number | null>(null);
  if (!rows.length) {
    return <Empty title={__('No changes yet', 'monoranks')} text={__('Fixes you approve in MonoRanks show up here, each with Undo.', 'monoranks')} />;
  }
  // Values keep their own direction (dir=auto) and clip at their end, so Latin text in a right-to-left admin reads normally.
  const cell = 'overflow-hidden text-ellipsis whitespace-nowrap text-start';
  return (
    <div className="overflow-x-auto rounded-b-2xl">
      <table className="w-full table-fixed border-collapse">
        <colgroup><col /><col className="w-[24%] max-[480px]:w-[36%]" /><col className="w-[16%] max-[480px]:w-[24%]" /><col className="w-[160px] max-[480px]:w-[64px]" /></colgroup>
        <thead><tr><th className={th}>{__('Page', 'monoranks')}</th><th className={th}>{__('Change', 'monoranks')}</th><th className={th}>{__('When', 'monoranks')}</th><th className={th}><span className="sr-only">{__('Undo', 'monoranks')}</span></th></tr></thead>
        <tbody>
          {rows.map((r) => {
            const isOpen = open === r.index;
            const id = `mr-change-${r.index}`;
            return (
              <Fragment key={r.index}>
                <tr className={`group/row ${isOpen ? '[&>td]:bg-tint' : 'hover:[&>td]:bg-hover'}`}>
                  <td className={td}>
                    <button type="button" aria-expanded={isOpen} aria-controls={id} onClick={() => setOpen(isOpen ? null : r.index)} className="flex w-full min-w-0 cursor-pointer items-center gap-2 border-0 bg-transparent p-0 text-start text-[14px] font-semibold text-ink">
                      <ChevronRight size={15} className={`mr-flip shrink-0 text-mute transition-transform ${isOpen ? 'rotate-90' : ''}`} aria-hidden="true" />
                      <span className={cell} dir="auto" title={r.where}>{r.where}</span>
                    </button>
                  </td>
                  <td className={`${td} max-[480px]:px-1`}><Badge className="max-w-full overflow-hidden text-ellipsis whitespace-nowrap" title={r.fieldLabel}>{r.fieldLabel}</Badge></td>
                  <td className={`${td} tabular-nums text-ink2`}><div className={cell} title={r.when}>{shortDate(r.at) || r.when}</div></td>
                  <td className={`${td} text-end`}><Button variant="ghost" size="sm" disabled={busy !== null} aria-busy={busy === r.index} onClick={() => onUndo(r)}>{busy !== r.index && <Undo2 size={14} className="mr-flip" />}<span className="max-[480px]:sr-only">{__('Undo', 'monoranks')}</span></Button></td>
                </tr>
                {isOpen && (
                  <tr id={id}>
                    <td colSpan={4} className="border-b border-grid bg-surface px-7 pb-6 pt-4 max-[782px]:px-[18px]">
                      <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,220px)] gap-2.5 max-[782px]:grid-cols-1">
                        <Detail label={__('Before', 'monoranks')} muted>{r.previous || '—'}</Detail>
                        <Detail label={__('After', 'monoranks')}>{r.value || '—'}</Detail>
                        <Detail label={__('By', 'monoranks')}>{r.actor || '—'}<span className="block text-[12px] font-normal text-mute">{r.when}</span></Detail>
                      </div>
                    </td>
                  </tr>
                )}
              </Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function Detail({ label, muted, children }: { label: string; muted?: boolean; children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-col gap-1.5 rounded-xl bg-surface2 px-4 py-3">
      <span className="text-[12px] font-bold uppercase tracking-[.06em] text-mute">{label}</span>
      <span className={`text-[14px] font-semibold [overflow-wrap:anywhere] ${muted ? 'text-ink2' : 'text-ink'}`} dir="auto">{children}</span>
    </div>
  );
}
