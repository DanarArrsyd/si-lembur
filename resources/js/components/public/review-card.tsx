import { useEffect, useRef } from 'react';
import { formatDuration, formatHours } from '@/lib/overtime';
import type { OvertimeReview } from '@/types/public';

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'Asia/Jakarta',
});

const formatDate = (date: string) =>
    dateFormatter.format(new Date(`${date}T00:00:00+07:00`));
const primaryButton =
    'min-h-[52px] w-full rounded-md bg-primary px-4 text-[17px] font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50';

export { primaryButton };

export function ReviewCard({
    review,
    onEdit,
}: {
    review: OvertimeReview;
    onEdit: () => void;
}) {
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => heading.current?.focus(), []);

    const nextDay = review.end_at.slice(0, 10);
    const rows = [
        { label: 'Departemen', value: review.department },
        {
            label: 'Nama',
            value: (
                <>
                    {review.employee.name}{' '}
                    <span className="font-mono text-sm text-ink-muted">
                        {review.employee.nik}
                    </span>
                </>
            ),
        },
        {
            label: 'Tanggal lembur',
            value: (
                <>
                    <span className="font-mono">{formatDate(review.date)}</span>{' '}
                    ·{' '}
                    {review.day_type === 'HARI_LIBUR'
                        ? 'Hari libur'
                        : 'Hari kerja'}
                </>
            ),
        },
        {
            label: 'Jam mulai',
            value: review.start_at.slice(11, 16),
            mono: true,
        },
        {
            label: 'Jam selesai',
            value:
                nextDay === review.date
                    ? review.end_at.slice(11, 16)
                    : `${review.end_at.slice(11, 16)} (${formatDate(nextDay)})`,
            mono: true,
        },
        {
            label: 'Istirahat',
            value: formatDuration(review.break_minutes),
            mono: true,
        },
        {
            label: 'Total lembur',
            value: `${formatDuration(review.total_minutes)} (${formatHours(review.total_minutes)})`,
            mono: true,
        },
        { label: 'Kegiatan lembur', value: review.description },
    ];

    return (
        <section className="grid gap-4" aria-labelledby="review-heading">
            <h2
                id="review-heading"
                ref={heading}
                tabIndex={-1}
                className="text-xl font-semibold text-ink"
            >
                Periksa lagi
            </h2>
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
            {(review.exceeds_daily_limit || review.exceeds_weekly_limit) && (
                <div className="grid gap-1 rounded-md bg-warning-soft p-3 text-sm text-warning">
                    {review.exceeds_daily_limit && (
                        <p>
                            Total lembur melebihi{' '}
                            {formatHours(review.daily_limit_minutes)} per hari.
                        </p>
                    )}
                    {review.exceeds_weekly_limit && (
                        <p>
                            Total lembur minggu ini melebihi{' '}
                            {formatHours(review.weekly_limit_minutes)}.
                        </p>
                    )}
                    <p>
                        Pengajuan tetap dikirim dan akan diperiksa supervisor.
                    </p>
                </div>
            )}
            <div className="sticky bottom-0 -mx-4 flex gap-2 border-t border-line bg-canvas px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <button type="button" className={primaryButton} disabled>
                    Kirim lembur
                </button>
                <button
                    type="button"
                    onClick={onEdit}
                    className="min-h-[52px] rounded-md border border-line-control bg-surface px-5 text-[17px] font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    Ubah
                </button>
            </div>
        </section>
    );
}
