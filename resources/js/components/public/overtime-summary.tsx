import {
    formatOvertimeDate,
    formatOvertimeEnd,
} from '@/lib/format-overtime-date';
import { formatDuration, formatHours } from '@/lib/overtime';
import type { OvertimeDetails } from '@/types/public';

export function OvertimeSummary({ details }: { details: OvertimeDetails }) {
    const rows = [
        { label: 'Departemen', value: details.department },
        {
            label: 'Nama',
            value: (
                <>
                    {details.employee.name}{' '}
                    <span className="font-mono text-sm text-ink-muted">
                        {details.employee.nik}
                    </span>
                </>
            ),
        },
        {
            label: 'Tanggal lembur',
            value: (
                <>
                    <span className="font-mono">
                        {formatOvertimeDate(details.date)}
                    </span>{' '}
                    ·{' '}
                    {details.day_type === 'HARI_LIBUR'
                        ? 'Hari libur'
                        : 'Hari kerja'}
                </>
            ),
        },
        {
            label: 'Jam mulai',
            value: details.start_at.slice(11, 16),
            mono: true,
        },
        {
            label: 'Jam selesai',
            value: formatOvertimeEnd(details.date, details.end_at),
            mono: true,
        },
        {
            label: 'Istirahat',
            value: formatDuration(details.break_minutes),
            mono: true,
        },
        {
            label: 'Total lembur',
            value: `${formatDuration(details.total_minutes)} (${formatHours(details.total_minutes)})`,
            mono: true,
        },
        { label: 'Kegiatan lembur', value: details.description },
    ];

    return (
        <dl className="divide-y divide-line rounded-md border border-line bg-surface px-4">
            {rows.map(({ label, value, mono }) => (
                <div key={label} className="grid gap-1 py-2.5">
                    <dt className="text-[15px] font-semibold text-ink-muted">
                        {label}
                    </dt>
                    <dd
                        className={
                            mono
                                ? 'font-mono text-[17px] break-words text-ink tabular-nums'
                                : 'text-[17px] break-words whitespace-pre-wrap text-ink'
                        }
                    >
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
