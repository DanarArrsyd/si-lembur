import type { Ref } from 'react';
import { formatHours } from '@/lib/overtime';
import type { OvertimeReview } from '@/types/public-overtime';
import { OvertimeSummary } from './overtime-summary';

export function ReviewCard({
    review,
    onEdit,
    onSubmit,
    processing,
    requestError,
    headingRef,
}: {
    review: OvertimeReview;
    onEdit: () => void;
    onSubmit: () => void;
    processing: boolean;
    requestError: string;
    headingRef: Ref<HTMLHeadingElement>;
}) {
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
            <OvertimeSummary summary={review} />
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
                {requestError && (
                    <p role="alert" className="text-sm text-danger">
                        {requestError}
                    </p>
                )}
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={processing}
                    className="h-[52px] rounded-md bg-primary px-4 text-[17px] font-semibold text-on-primary hover:bg-primary-hover disabled:opacity-50"
                >
                    {processing ? 'Mengirim…' : 'Kirim lembur'}
                </button>
                <button
                    type="button"
                    onClick={onEdit}
                    disabled={processing}
                    className="h-[52px] rounded-md border border-line-control bg-surface px-4 text-[17px] font-semibold hover:bg-surface-sunken disabled:opacity-50"
                >
                    Ubah
                </button>
            </div>
        </section>
    );
}
