export function KpiTile({
    label,
    value,
    detail,
}: {
    label: string;
    value: number | string;
    detail: string;
}) {
    return (
        <div className="grid content-start gap-1 rounded-md border border-line bg-surface p-4">
            <dt className="text-[11px] leading-4 font-semibold tracking-[0.06em] text-ink-muted uppercase">
                {label}
            </dt>
            <dd className="font-mono text-[28px] leading-9 font-semibold text-ink tabular-nums">
                {value}
            </dd>
            <dd className="text-xs text-ink-muted">{detail}</dd>
        </div>
    );
}
