import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { OvertimeSummary } from '@/components/public/overtime-summary';
import { StatusPill } from '@/components/status-pill';
import PublicLayout from '@/layouts/public-layout';
import { home } from '@/routes';
import type { OvertimeSubmission } from '@/types/public-overtime';

export default function SubmissionSuccess({
    submission,
}: {
    submission: OvertimeSubmission | null;
}) {
    const heading = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        heading.current?.focus();
    }, []);

    return (
        <section aria-labelledby="submission-title" className="space-y-4">
            <h1
                id="submission-title"
                ref={heading}
                tabIndex={-1}
                className="text-[22px] leading-[30px] font-bold"
            >
                {submission ? 'Lembur terkirim' : 'Tautan tidak berlaku'}
            </h1>
            {submission ? (
                <>
                    <div className="space-y-2 rounded-md border border-line bg-surface p-4">
                        <p className="text-[15px] font-semibold">
                            Nomor pengajuan
                        </p>
                        <p className="tabular font-mono text-[22px] leading-8 font-semibold wrap-break-word">
                            {submission.number}
                        </p>
                        <StatusPill status={submission.status} />
                        {submission.status === 'PENDING' && (
                            <p className="text-[17px]">
                                Menunggu persetujuan supervisor.
                            </p>
                        )}
                    </div>
                    <OvertimeSummary summary={submission} />
                </>
            ) : (
                <p className="text-[17px] text-ink-muted">
                    Tautan bukti pengajuan berlaku selama 24 jam. Untuk cek
                    status pengajuan, hubungi supervisor departemen.
                </p>
            )}
            <div className="sticky bottom-0 -mx-4 grid gap-2 border-t border-line bg-surface px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <a
                    href={home.url()}
                    rel="noreferrer"
                    className="flex min-h-[52px] items-center justify-center rounded-md bg-primary px-4 text-[17px] font-semibold text-on-primary hover:bg-primary-hover"
                >
                    {submission ? 'Isi lembur lagi' : 'Kembali ke form'}
                </a>
                {submission && (
                    <button
                        type="button"
                        disabled
                        className="h-[52px] rounded-md border border-line-control bg-surface px-4 text-[17px] font-semibold opacity-50"
                    >
                        Cek status
                    </button>
                )}
            </div>
        </section>
    );
}

SubmissionSuccess.layout = (page: ReactNode) => (
    <PublicLayout title="Bukti pengajuan">{page}</PublicLayout>
);
