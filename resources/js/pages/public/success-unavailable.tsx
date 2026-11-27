import { Link } from '@inertiajs/react';
import { primaryAction } from '@/components/public/action-styles';
import PublicLayout from '@/layouts/public-layout';

export default function OvertimeSuccessUnavailable() {
    return (
        <section aria-labelledby="unavailable-heading" className="grid gap-4">
            <div className="grid gap-1">
                <h2
                    id="unavailable-heading"
                    className="text-xl font-semibold text-ink"
                >
                    Tautan pengajuan tidak berlaku
                </h2>
                <p className="text-[17px] text-ink-muted">
                    Buka Cek status untuk melihat pengajuan lembur.
                </p>
            </div>
            <Link href="/status" className={primaryAction}>
                Cek status
            </Link>
        </section>
    );
}

OvertimeSuccessUnavailable.layout = [
    PublicLayout,
    { title: 'Tautan pengajuan tidak berlaku' },
];
