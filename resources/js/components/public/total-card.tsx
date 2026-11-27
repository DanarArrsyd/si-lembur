import { formatDuration, formatHours } from '@/lib/overtime';

export function TotalCard({
    minutes,
    invalid = false,
}: {
    minutes: number | null;
    invalid?: boolean;
}) {
    return (
        <div className="rounded-md border border-line bg-primary-soft px-4 py-3">
            <p className="text-[15px] font-semibold">Total lembur</p>
            <div aria-live="polite" aria-atomic="true">
                <p className="tabular font-mono text-[28px] leading-9 font-semibold">
                    {minutes === null ? '—' : formatDuration(minutes)}
                </p>
                {minutes !== null && (
                    <p className="text-sm text-ink-muted">
                        {formatHours(minutes)}
                    </p>
                )}
            </div>
            {minutes === null && !invalid && (
                <p className="text-sm text-ink-muted">
                    Pilih jam mulai dan jam selesai.
                </p>
            )}
        </div>
    );
}
