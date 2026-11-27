import clsx from 'clsx';
import type { OvertimeStatus } from '@/types/public';

const statuses = {
    PENDING: { label: 'Menunggu', color: 'bg-warning-soft text-warning' },
    APPROVED: { label: 'Disetujui', color: 'bg-success-soft text-success' },
    REJECTED: { label: 'Ditolak', color: 'bg-danger-soft text-danger' },
} satisfies Record<OvertimeStatus, { label: string; color: string }>;

export function StatusPill({ status }: { status: OvertimeStatus }) {
    const { label, color } = statuses[status];

    return (
        <span
            className={clsx(
                'inline-flex h-[22px] items-center gap-1.5 rounded-full px-2 text-[11px] font-semibold tracking-[0.06em] uppercase',
                color,
            )}
        >
            <span
                aria-hidden="true"
                className="size-1.5 rounded-full bg-current"
            />
            {label}
        </span>
    );
}
