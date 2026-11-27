import { useEffect, useRef } from 'react';
import {
    primaryAction,
    secondaryAction,
} from '@/components/public/action-styles';
import { OvertimeSummary } from '@/components/public/overtime-summary';
import { formatHours } from '@/lib/overtime';
import type { OvertimeReview } from '@/types/public';

export function ReviewCard({
    review,
    onEdit,
    onSubmit,
    processing,
    requestError,
}: {
    review: OvertimeReview;
    onEdit: () => void;
    onSubmit: () => void;
    processing: boolean;
    requestError: string;
}) {
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => heading.current?.focus(), []);

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
            <OvertimeSummary details={review} />
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
            {requestError && (
                <p className="text-sm text-danger" role="alert">
                    {requestError}
                </p>
            )}
            <div className="sticky bottom-0 -mx-4 flex gap-2 border-t border-line bg-canvas px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <button
                    type="button"
                    className={primaryAction}
                    onClick={onSubmit}
                    disabled={processing}
                >
                    {processing ? 'Mengirim…' : 'Kirim lembur'}
                </button>
                <button
                    type="button"
                    onClick={onEdit}
                    disabled={processing}
                    className={secondaryAction}
                >
                    Ubah
                </button>
            </div>
        </section>
    );
}
