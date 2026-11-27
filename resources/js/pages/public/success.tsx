import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import {
    primaryAction,
    secondaryAction,
} from '@/components/public/action-styles';
import { OvertimeSummary } from '@/components/public/overtime-summary';
import { StatusPill } from '@/components/public/status-pill';
import PublicLayout from '@/layouts/public-layout';
import type { OvertimeDetails, OvertimeStatus } from '@/types/public';

export default function OvertimeSuccess({
    number,
    status,
    details,
    statusUrl,
}: {
    number: string;
    status: OvertimeStatus;
    details: OvertimeDetails;
    statusUrl: string;
}) {
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => heading.current?.focus(), []);

    return (
        <section className="grid gap-4" aria-labelledby="success-heading">
            <div className="grid gap-1">
                <h2
                    ref={heading}
                    id="success-heading"
                    tabIndex={-1}
                    className="text-xl font-semibold text-ink"
                >
                    Lembur terkirim.
                </h2>
                {status === 'PENDING' && (
                    <p className="text-[17px] text-ink-muted">
                        Menunggu persetujuan supervisor.
                    </p>
                )}
            </div>
            <div className="flex flex-wrap items-end justify-between gap-3 rounded-md bg-primary-soft p-4">
                <div>
                    <p className="text-[15px] font-semibold text-ink">
                        Nomor pengajuan
                    </p>
                    <p className="font-mono text-lg font-semibold text-primary tabular-nums">
                        {number}
                    </p>
                </div>
                <StatusPill status={status} />
            </div>

            <OvertimeSummary details={details} />

            <div className="sticky bottom-0 -mx-4 grid gap-2 border-t border-line bg-canvas px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <Link href="/" className={primaryAction}>
                    Isi lembur lagi
                </Link>
                <Link href={statusUrl} className={secondaryAction}>
                    Cek status
                </Link>
            </div>
        </section>
    );
}

OvertimeSuccess.layout = [PublicLayout, { title: 'Lembur terkirim' }];
