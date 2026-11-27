const statuses = {
    PENDING: { label: 'Menunggu', colors: 'bg-warning-soft text-warning' },
    APPROVED: { label: 'Disetujui', colors: 'bg-success-soft text-success' },
    REJECTED: { label: 'Ditolak', colors: 'bg-danger-soft text-danger' },
};

export function StatusPill({ status }: { status: keyof typeof statuses }) {
    const { label, colors } = statuses[status];

    return (
        <span
            className={`inline-flex h-[22px] items-center gap-1.5 rounded-full px-2 text-[11px] font-semibold tracking-[0.06em] uppercase ${colors}`}
        >
            <span
                aria-hidden="true"
                className="size-1.5 rounded-full bg-current"
            />
            {label}
        </span>
    );
}
