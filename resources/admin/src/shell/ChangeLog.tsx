import { __ } from '@wordpress/i18n';
import { Undo2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Empty } from '@/shell/Shell';
import type { LogRow } from '@/lib/api';

/** Recent changes written by MonoRanks, newest first, each with Undo. */
export function ChangeLog({ rows, onUndo, busy }: { rows: LogRow[]; onUndo: (row: LogRow) => void; busy: number | null }) {
  if (!rows.length) {
    return <Empty title={__('No changes yet', 'monoranks')} text={__('Fixes you approve in MonoRanks show up here, each with Undo.', 'monoranks')} />;
  }
  const th = 'whitespace-nowrap border-b border-border px-[12px] py-[11px] text-start text-[12px] font-medium text-mute';
  const td = 'border-b border-grid px-[12px] py-3 align-middle text-[12px] group-last:border-b-0';
  // Values keep their own direction (dir=auto) and clip at their end, so Latin text in a right-to-left admin reads normally.
  const cell = 'overflow-hidden text-ellipsis whitespace-nowrap text-start';
  return (
    <div className="rounded-b-xl">
      <table className="w-full table-fixed border-collapse">
        <colgroup><col className="w-[15%] min-w-[130px]" /><col className="w-[12%]" /><col className="w-[12%]" /><col className="w-[18%]" /><col /><col /><col className="w-[96px]" /></colgroup>
        <thead><tr><th className={th}>{__('When', 'monoranks')}</th><th className={th}>{__('Who', 'monoranks')}</th><th className={th}>{__('Field', 'monoranks')}</th><th className={th}>{__('Where', 'monoranks')}</th><th className={th}>{__('Before', 'monoranks')}</th><th className={th}>{__('After', 'monoranks')}</th><th className={th}></th></tr></thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.index} className="group hover:[&>td]:bg-surface2">
              <td className={`${td} tabular-nums`}><div className={cell} title={r.when}>{r.when}</div></td>
              <td className={td}><div className={cell} title={r.actor}>{r.actor}</div></td>
              <td className={td}><Badge className="max-w-full overflow-hidden text-ellipsis whitespace-nowrap" title={r.fieldLabel}>{r.fieldLabel}</Badge></td>
              <td className={td}><div className={cell} dir="auto" title={r.where}>{r.where}</div></td>
              <td className={`${td} text-mute`}><div className={cell} dir="auto" title={r.previous}>{r.previous || '—'}</div></td>
              <td className={td}><div className={cell} dir="auto" title={r.value}>{r.value || '—'}</div></td>
              <td className={`${td} text-end`}><Button variant="ghost" size="sm" disabled={busy !== null} aria-busy={busy === r.index} onClick={() => onUndo(r)}>{busy !== r.index && <Undo2 size={13} className="mr-flip" />}{__('Undo', 'monoranks')}</Button></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
