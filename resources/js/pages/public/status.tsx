import type { ReactNode } from 'react';
import { StatusPill } from '@/components/status-pill';
import PublicLayout from '@/layouts/public-layout';
import { formatDate, formatEndTime } from '@/lib/date-display';
import { formatDuration } from '@/lib/overtime';
import { home } from '@/routes';
import type { OvertimeHistory } from '@/types/public-overtime';

export default function StatusPage({
    history,
    invalidLink = false,
}: {
    history: OvertimeHistory | null;
    invalidLink?: boolean;
}) {
    return (
        <section aria-labelledby="status-title" className="space-y-4">
            <h1
                id="status-title"
                className="text-[22px] leading-[30px] font-bold"
            >
                {invalidLink ? 'Tautan tidak berlaku' : 'Cek status pengajuan'}
            </h1>
            {history ? (
                <>
                    <div className="space-y-1">
                        <p className="text-[17px] font-semibold wrap-break-word">
                            {history.employeeName}
                        </p>
                        <p className="text-sm text-ink-muted">
                            Simpan tautan pribadi ini untuk cek status lagi.
                        </p>
                    </div>
                    {history.items.length === 0 ? (
                        <p className="rounded-md border border-line bg-surface p-4 text-[17px]">
                            Belum ada pengajuan dalam 60 hari terakhir.
                        </p>
                    ) : (
                        <ul
                            aria-label="Pengajuan dalam 60 hari terakhir"
                            className="space-y-3"
                        >
                            {history.items.map((item) => (
                                <li
                                    key={item.number}
                                    className="space-y-2 rounded-md border border-line bg-surface p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h2 className="tabular font-mono text-[17px] font-semibold">
                                            {formatDate(item.date)}
                                        </h2>
                                        <StatusPill status={item.status} />
                                    </div>
                                    <p className="tabular font-mono text-sm text-ink-muted">
                                        {item.number}
                                    </p>
                                    <p className="tabular font-mono text-[17px]">
                                        {item.start_at.slice(11)}–
                                        {formatEndTime(item.date, item.end_at)}
                                    </p>
                                    <p className="tabular font-mono text-[17px] font-semibold">
                                        {formatDuration(item.total_minutes)}
                                    </p>
                                    {item.reject_reason && (
                                        <div className="border-t border-line pt-2">
                                            <p className="text-sm font-semibold text-ink-muted">
                                                Alasan penolakan
                                            </p>
                                            <p className="mt-1 text-[17px] wrap-break-word whitespace-pre-line">
                                                {item.reject_reason}
                                            </p>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </>
            ) : (
                <div className="space-y-3 rounded-md border border-line bg-surface p-4 text-[17px]">
                    <p>
                        Buka halaman bukti pengajuan, lalu pilih{' '}
                        <strong>Cek status</strong> untuk mendapatkan tautan
                        pribadi. Tautan status berlaku 60 hari sejak pengajuan.
                    </p>
                    <p>
                        Jika tautan sudah tidak tersedia, hubungi supervisor
                        departemen.
                    </p>
                </div>
            )}
            <a
                href={home.url()}
                rel="noreferrer"
                className="flex min-h-[52px] items-center justify-center rounded-md border border-line-control bg-surface px-4 text-[17px] font-semibold hover:bg-surface-sunken"
            >
                Kembali ke form lembur
            </a>
        </section>
    );
}

StatusPage.layout = (page: ReactNode) => (
    <PublicLayout title="Cek status" showStatusLink={false}>
        {page}
    </PublicLayout>
);
