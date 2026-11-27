import type { Ref } from 'react';
import { formatDuration, formatHours } from '@/lib/overtime';
import type { OvertimeReview } from '@/types/public-overtime';
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

export function ReviewCard({
    review,
    onEdit,
    headingRef,
}: {
    review: OvertimeReview;
    onEdit: () => void;
    headingRef: Ref<HTMLHeadingElement>;
}) {
    const endDate = review.end_at.slice(0, 10);
    const details = [
        ['Departemen', review.department],
        ['Nama', review.employee.name],
        ['NIK', review.employee.nik],
        ['Tanggal lembur', formatDate(review.date)],
        ['Jam mulai', review.start_at.slice(11)],
        [
            'Jam selesai',
            endDate === review.date
                ? review.end_at.slice(11)
                : `${review.end_at.slice(11)} · ${formatDate(endDate)}`,
        ],
        ['Istirahat', formatDuration(review.break_minutes)],
        ['Kegiatan lembur', review.description],
    ];

    return (
        <section aria-labelledby="review-title" className="space-y-4">
            <h1
                id="review-title"
                ref={headingRef}
                tabIndex={-1}
                className="text-[22px] leading-[30px] font-bold"
            >
                Periksa lagi
            </h1>
            <dl className="divide-y divide-line rounded-md border border-line bg-surface px-4">
                {details.map(([label, value], index) => (
                    <div key={label} className="py-2.5">
                        <dt className="text-sm font-semibold text-ink-muted">
                            {label}
                        </dt>
                        <dd
                            className={
                                index >= 2 && index <= 6
                                    ? 'tabular mt-1 font-mono text-[17px] wrap-break-word'
                                    : 'mt-1 text-[17px] wrap-break-word whitespace-pre-line'
                            }
                        >
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <TotalCard minutes={review.total_minutes} />
            {(review.exceeds_daily_limit || review.exceeds_weekly_limit) && (
                <div className="space-y-2 rounded-md border border-line bg-primary-soft p-4 text-sm">
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
                        Pengajuan tetap bisa dikirim dan akan diperiksa
                        supervisor.
                    </p>
                </div>
            )}
            <div className="sticky bottom-0 -mx-4 grid gap-2 border-t border-line bg-surface px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <p id="review-availability" className="text-sm text-ink-muted">
                    Pengiriman belum tersedia.
                </p>
                <button
                    type="button"
                    disabled
                    aria-describedby="review-availability"
                    className="h-[52px] rounded-md bg-primary px-4 text-[17px] font-semibold text-on-primary opacity-50"
                >
                    Kirim lembur
                </button>
                <button
                    type="button"
                    onClick={onEdit}
                    className="h-[52px] rounded-md border border-line-control bg-surface px-4 text-[17px] font-semibold hover:bg-surface-sunken"
                >
                    Ubah
                </button>
            </div>
        </section>
    );
}
