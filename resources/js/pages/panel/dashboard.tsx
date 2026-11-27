import { Link } from '@inertiajs/react';
import { KpiTile } from '@/components/panel/kpi-tile';
import { OvertimeList } from '@/components/panel/overtime-list';
import PanelLayout from '@/layouts/panel-layout';
import { formatHours } from '@/lib/overtime';
import type { DashboardProps } from '@/types/panel-overtime';

export default function Dashboard({
    monthLabel,
    kpis,
    recentPending,
    approvalsUrl,
}: DashboardProps) {
    return (
        <div className="grid gap-6">
            <dl
                aria-label="Ringkasan lembur"
                className="grid grid-cols-2 gap-3 xl:grid-cols-4"
            >
                <KpiTile
                    label="Menunggu"
                    value={kpis.pendingCount}
                    detail="Semua tanggal"
                />
                <KpiTile
                    label="Disetujui bulan ini"
                    value={kpis.approvedCount}
                    detail={monthLabel}
                />
                <KpiTile
                    label="Total jam bulan ini"
                    value={formatHours(kpis.approvedMinutes)}
                    detail={monthLabel}
                />
                <KpiTile
                    label="Lewat batas"
                    value={kpis.overLimitCount}
                    detail="Dari pengajuan Menunggu"
                />
            </dl>

            <section aria-labelledby="pending-heading" className="grid gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2
                        id="pending-heading"
                        className="text-base font-semibold text-ink"
                    >
                        Menunggu persetujuan
                    </h2>
                    {approvalsUrl && (
                        <Link
                            href={approvalsUrl}
                            className="inline-flex min-h-11 items-center rounded-sm text-sm font-semibold text-primary-hover underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            Buka persetujuan
                        </Link>
                    )}
                </div>
                {recentPending.length > 0 ? (
                    <OvertimeList
                        rows={recentPending}
                        label="Pengajuan terbaru yang menunggu persetujuan"
                    />
                ) : (
                    <p className="rounded-md border border-line bg-surface p-4 text-sm text-ink-muted">
                        Belum ada pengajuan yang menunggu persetujuan.
                    </p>
                )}
            </section>
        </div>
    );
}

Dashboard.layout = [PanelLayout, { title: 'Dashboard' }];
