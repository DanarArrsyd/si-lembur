import { StatusPill } from '@/components/public/status-pill';
import {
    formatOvertimeDate,
    formatOvertimeEnd,
} from '@/lib/format-overtime-date';
import { formatDuration } from '@/lib/overtime';
import type { PanelOvertimeRow } from '@/types/panel-overtime';

function OvertimeHours({ row }: { row: PanelOvertimeRow }) {
    return (
        <span className="font-mono tabular-nums">
            {row.start_at.slice(11, 16)}–
            {formatOvertimeEnd(row.date, row.end_at)}
        </span>
    );
}

function LimitFlags({ row }: { row: PanelOvertimeRow }) {
    return (
        <>
            {row.exceeds_daily_limit && (
                <p className="text-xs font-medium text-warning">
                    Lewat batas harian
                </p>
            )}
            {row.exceeds_weekly_limit && (
                <p className="text-xs font-medium text-warning">
                    Lewat batas mingguan
                </p>
            )}
        </>
    );
}

export function OvertimeList({
    rows,
    label,
}: {
    rows: PanelOvertimeRow[];
    label: string;
}) {
    return (
        <>
            <ul aria-label={label} className="grid gap-3 sm:hidden">
                {rows.map((row) => (
                    <li
                        key={row.id}
                        className="grid min-w-0 gap-2 rounded-md border border-line bg-surface p-4 text-sm text-ink"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <p className="font-semibold">{row.employee.name}</p>
                            <StatusPill status={row.status} />
                        </div>
                        <p className="text-xs text-ink-muted">
                            {row.department.name} ·{' '}
                            <span className="font-mono tabular-nums">
                                {row.number}
                            </span>
                        </p>
                        <p className="font-mono tabular-nums">
                            {formatOvertimeDate(row.date)}
                        </p>
                        <OvertimeHours row={row} />
                        <p className="font-mono font-semibold tabular-nums">
                            {formatDuration(row.total_minutes)}
                        </p>
                        <LimitFlags row={row} />
                    </li>
                ))}
            </ul>

            <div className="hidden overflow-x-auto rounded-md border border-line bg-surface sm:block">
                <table
                    aria-label={label}
                    className="w-full text-left text-sm text-ink"
                >
                    <thead className="sticky top-0 bg-surface-sunken text-[11px] font-semibold tracking-[0.06em] text-ink-muted uppercase">
                        <tr>
                            <th scope="col" className="px-3 py-2.5">
                                Karyawan
                            </th>
                            <th scope="col" className="px-3 py-2.5">
                                Tanggal dan jam
                            </th>
                            <th scope="col" className="px-3 py-2.5 text-right">
                                Total
                            </th>
                            <th scope="col" className="px-3 py-2.5">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {rows.map((row) => (
                            <tr
                                key={row.id}
                                className="border-t border-line align-top"
                            >
                                <th
                                    scope="row"
                                    className="px-3 py-2 font-normal"
                                >
                                    <p className="font-semibold">
                                        {row.employee.name}
                                    </p>
                                    <p className="text-xs text-ink-muted">
                                        {row.department.name} ·{' '}
                                        <span className="font-mono tabular-nums">
                                            {row.number}
                                        </span>
                                    </p>
                                </th>
                                <td className="px-3 py-2">
                                    <p className="font-mono tabular-nums">
                                        {formatOvertimeDate(row.date)}
                                    </p>
                                    <OvertimeHours row={row} />
                                </td>
                                <td className="px-3 py-2 text-right font-mono tabular-nums">
                                    {formatDuration(row.total_minutes)}
                                </td>
                                <td className="px-3 py-2">
                                    <StatusPill status={row.status} />
                                    <LimitFlags row={row} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
