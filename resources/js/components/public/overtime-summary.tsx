import { formatDate, formatEndTime } from '@/lib/date-display';
import { formatDuration } from '@/lib/overtime';
import type { OvertimeSummary as Summary } from '@/types/public-overtime';
import { TotalCard } from './total-card';

export function OvertimeSummary({ summary }: { summary: Summary }) {
    const details = [
        { label: 'Departemen', value: summary.department },
        { label: 'Nama', value: summary.employee.name },
        { label: 'NIK', value: summary.employee.nik, mono: true },
        {
            label: 'Tanggal lembur',
            value: formatDate(summary.date),
            mono: true,
        },
        { label: 'Jam mulai', value: summary.start_at.slice(11), mono: true },
        {
            label: 'Jam selesai',
            value: formatEndTime(summary.date, summary.end_at),
            mono: true,
        },
        {
            label: 'Istirahat',
            value: formatDuration(summary.break_minutes),
            mono: true,
        },
        { label: 'Kegiatan lembur', value: summary.description },
    ];

    return (
        <>
            <dl className="divide-y divide-line rounded-md border border-line bg-surface px-4">
                {details.map(({ label, value, mono }) => (
                    <div key={label} className="py-2.5">
                        <dt className="text-sm font-semibold text-ink-muted">
                            {label}
                        </dt>
                        <dd
                            className={
                                mono
                                    ? 'tabular mt-1 font-mono text-[17px] wrap-break-word'
                                    : 'mt-1 text-[17px] wrap-break-word whitespace-pre-line'
                            }
                        >
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <TotalCard minutes={summary.total_minutes} />
        </>
    );
}
