import { Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { OvertimeList } from '@/components/panel/overtime-list';
import { OvertimeReasonDialog } from '@/components/panel/overtime-reason-dialog';
import { PanelPagination } from '@/components/panel/panel-pagination';
import { Button, buttonVariants } from '@/components/ui/button';
import PanelLayout from '@/layouts/panel-layout';
import { formatOvertimeDate } from '@/lib/format-overtime-date';
import type {
    OvertimeHistoryProps,
    OvertimeHistoryRow,
} from '@/types/panel-overtime';

const filterControl =
    'h-11 w-full min-w-0 rounded-sm border border-line-control bg-surface px-3 text-base text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
const outsideWindow = 'Di luar periode pembatalan. Hubungi HRD untuk koreksi.';

export default function OvertimeHistory({
    history,
    filters,
    employees,
    cancellationWindow,
    errors,
}: OvertimeHistoryProps) {
    const [target, setTarget] = useState<OvertimeHistoryRow | null>(null);
    const [busy, setBusy] = useState(false);
    const [reasonError, setReasonError] = useState<string>();
    const requestActive = useRef(false);
    const heading = useRef<HTMLHeadingElement>(null);
    const trigger = useRef<HTMLButtonElement>(null);
    const message =
        !busy && (errors?.overtime_date ? outsideWindow : errors?.status);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const data: Record<string, string> = {};
        for (const field of ['month', 'status', 'employee_id']) {
            const value = form.get(field);
            if (typeof value === 'string' && value !== '') data[field] = value;
        }

        router.get('/panel/overtimes', data);
    }

    function cancelApproval(row: OvertimeHistoryRow, reason: string) {
        if (requestActive.current) return;
        requestActive.current = true;
        setBusy(true);
        setReasonError(undefined);

        const query = new URLSearchParams({ month: filters.month });
        if (filters.status) query.set('status', filters.status);
        if (filters.employee_id)
            query.set('employee_id', String(filters.employee_id));
        if (history.current_page > 1)
            query.set('page', String(history.current_page));

        router.post(
            `/panel/overtimes/${row.id}/cancel-approval?${query}`,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => setTarget(null),
                onError: (responseErrors) => {
                    if (responseErrors.reason)
                        setReasonError(responseErrors.reason);
                    else setTarget(null);
                },
                onNetworkError: () => {
                    setReasonError(
                        'Sambungan terputus. Periksa koneksi lalu coba lagi.',
                    );
                    return false;
                },
                onFinish: () => {
                    requestActive.current = false;
                    setBusy(false);
                },
            },
        );
    }

    function actions(row: OvertimeHistoryRow) {
        if (row.status === 'PENDING')
            return (
                <Link
                    href={'/panel/overtimes/' + row.id + '/edit'}
                    aria-label={'Edit ' + row.number}
                    className={buttonVariants({
                        variant: 'secondary',
                        size: 'lg',
                    })}
                >
                    Edit
                </Link>
            );
        if (row.status !== 'APPROVED') return null;
        if (!row.can_cancel_approval) {
            return <p className="text-xs text-ink-muted">{outsideWindow}</p>;
        }

        return (
            <Button
                size="lg"
                variant="danger"
                className="max-w-full whitespace-normal"
                aria-label={`Batalkan persetujuan ${row.number}`}
                disabled={busy}
                onClick={(event) => {
                    trigger.current = event.currentTarget;
                    setReasonError(undefined);
                    setTarget(row);
                }}
            >
                Batalkan persetujuan
            </Button>
        );
    }

    return (
        <section
            aria-labelledby="history-heading"
            className="grid min-w-0 gap-4"
        >
            <h2
                id="history-heading"
                ref={heading}
                tabIndex={-1}
                className="text-lg font-semibold text-ink"
            >
                Riwayat pengajuan
            </h2>

            <form
                onSubmit={applyFilters}
                className="grid gap-3 rounded-md border border-line bg-surface p-4 sm:grid-cols-[repeat(3,minmax(0,1fr))_auto] sm:items-end"
            >
                <div className="grid min-w-0 gap-1.5">
                    <label
                        htmlFor="history-month"
                        className="text-sm font-semibold text-ink"
                    >
                        Bulan lembur
                    </label>
                    <input
                        type="month"
                        id="history-month"
                        name="month"
                        className={filterControl}
                        defaultValue={filters.month}
                    />
                </div>
                <div className="grid min-w-0 gap-1.5">
                    <label
                        htmlFor="history-status"
                        className="text-sm font-semibold text-ink"
                    >
                        Status
                    </label>
                    <select
                        id="history-status"
                        name="status"
                        className={filterControl}
                        defaultValue={filters.status ?? ''}
                    >
                        <option value="">Semua status</option>
                        <option value="PENDING">Menunggu</option>
                        <option value="APPROVED">Disetujui</option>
                        <option value="REJECTED">Ditolak</option>
                    </select>
                </div>
                <div className="grid min-w-0 gap-1.5">
                    <label
                        htmlFor="history-employee_id"
                        className="text-sm font-semibold text-ink"
                    >
                        Karyawan
                    </label>
                    <select
                        id="history-employee_id"
                        name="employee_id"
                        className={filterControl}
                        defaultValue={filters.employee_id ?? ''}
                    >
                        <option value="">Semua karyawan</option>
                        {employees.map((employee) => (
                            <option key={employee.id} value={employee.id}>
                                {employee.name} · {employee.nik}
                            </option>
                        ))}
                    </select>
                </div>
                <Button type="submit" size="lg" disabled={busy}>
                    Terapkan filter
                </Button>
            </form>

            <div className="flex flex-wrap justify-between gap-2 text-sm text-ink-muted">
                <p>{history.total} pengajuan</p>
                <p>
                    Periode pembatalan{' '}
                    {formatOvertimeDate(cancellationWindow.min)}–
                    {formatOvertimeDate(cancellationWindow.max)}
                </p>
            </div>
            {message && (
                <p
                    className="rounded-md bg-danger-soft p-3 text-sm text-danger"
                    role="alert"
                >
                    {message}
                </p>
            )}

            {history.data.length > 0 ? (
                <OvertimeList
                    rows={history.data}
                    label="Riwayat lembur"
                    renderActions={actions}
                />
            ) : (
                <p className="rounded-md border border-line bg-surface p-4 text-sm text-ink-muted">
                    Belum ada pengajuan yang cocok dengan filter.
                </p>
            )}
            <PanelPagination
                page={history}
                label="Halaman riwayat"
                busy={busy}
            />

            {target && (
                <OvertimeReasonDialog
                    row={target}
                    action="cancel-approval"
                    busy={busy}
                    serverError={reasonError}
                    onSubmit={(reason) => cancelApproval(target, reason)}
                    onClose={() => setTarget(null)}
                    restoreFocus={() => {
                        if (
                            trigger.current?.isConnected &&
                            !trigger.current.disabled
                        )
                            trigger.current.focus();
                        else heading.current?.focus();
                    }}
                />
            )}
        </section>
    );
}

OvertimeHistory.layout = [PanelLayout, { title: 'Riwayat lembur' }];
