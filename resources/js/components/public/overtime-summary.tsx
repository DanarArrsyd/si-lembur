import { formatDuration } from '@/lib/overtime';
import type { OvertimeSummary as Summary } from '@/types/public-overtime';
import { TotalCard } from './total-card';

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

function formatDate(date: string) {
    return dateFormatter.format(new Date(`${date}T00:00:00Z`));
}

export function OvertimeSummary({ summary }: { summary: Summary }) {
    const endDate = summary.end_at.slice(0, 10);
    const endTime = summary.end_at.slice(11);
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
            value:
                endDate === summary.date
                    ? endTime
                    : `${endTime} · ${formatDate(endDate)}`,
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
