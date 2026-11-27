// Placeholder Fase 1. KPI dan daftar pengajuan menunggu dibangun di Fase 4.
import PanelLayout from '@/layouts/panel-layout';

export default function Dashboard() {
    return (
        <section className="max-w-2xl rounded-md border border-line bg-surface p-5">
            <h2 className="text-base font-semibold">Belum ada data lembur</h2>
            <p className="mt-1 text-sm text-ink-muted">
                Ringkasan dan daftar pengajuan yang menunggu persetujuan tampil
                di sini setelah fitur persetujuan selesai dibangun.
            </p>
        </section>
    );
}

Dashboard.layout = [PanelLayout, { title: 'Dashboard' }];
