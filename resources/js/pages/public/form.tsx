import type { ReactNode } from 'react';
import PublicLayout from '@/layouts/public-layout';

// Placeholder Fase 1. Form input lembur dibangun di Fase 3.
export default function OvertimeForm() {
    return (
        <section className="rounded-md border border-line bg-surface p-4">
            <h2 className="text-base font-semibold">Form sedang disiapkan</h2>
            <p className="mt-1 text-[17px] leading-6 text-ink-muted">
                Pengisian lembur lewat halaman ini belum bisa dipakai. Untuk
                sementara, laporkan lembur ke supervisor departemen.
            </p>
        </section>
    );
}

OvertimeForm.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>;
