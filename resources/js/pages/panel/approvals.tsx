import { Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { PanelPagination } from '@/components/panel/panel-pagination';
import { OvertimeList } from '@/components/panel/overtime-list';
import { OvertimeReasonDialog } from '@/components/panel/overtime-reason-dialog';
import { Button, buttonVariants } from '@/components/ui/button';
import PanelLayout from '@/layouts/panel-layout';
import type { ApprovalQueue, PanelOvertimeRow } from '@/types/panel-overtime';

const checkboxStyle =
    'size-4 accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

function ApprovalPage({
    queue,
    statusError,
}: {
    queue: ApprovalQueue;
    statusError?: string;
}) {
    const [selected, setSelected] = useState<number[]>([]);
    const [rejecting, setRejecting] = useState<PanelOvertimeRow | null>(null);
    const [processing, setProcessing] = useState(false);
    const [actionError, setActionError] = useState('');
    const [reasonError, setReasonError] = useState<string>();
    const message = processing ? '' : actionError || statusError;
    const heading = useRef<HTMLHeadingElement>(null);
    const rejectTrigger = useRef<HTMLButtonElement>(null);
    const submitting = useRef(false);
    const visibleIds = queue.data.map((row) => row.id);
    const selectedIds = visibleIds.filter((id) => selected.includes(id));
    const allSelected =
        visibleIds.length > 0 && selectedIds.length === visibleIds.length;

    function submit(
        path: string,
        data: { ids?: number[]; reason?: string } = {},
    ) {
        if (submitting.current) return;
        submitting.current = true;
        setProcessing(true);
        setActionError('');
        setReasonError(undefined);

        const page =
            queue.current_page > 1 ? `?page=${queue.current_page}` : '';
        router.post(`/panel/approvals/${path}${page}`, data, {
            preserveScroll: true,
            onSuccess: () => {
                setSelected([]);
                setRejecting(null);
            },
            onError: (errors) => {
                if (errors.reason) {
                    setReasonError(errors.reason);
                    return;
                }

                setRejecting(null);
                setActionError(
                    Object.values(errors).find(Boolean) ??
                        'Pengajuan gagal diproses.',
                );
                if (errors.status) setSelected([]);
            },
            onNetworkError: () => {
                const error =
                    'Sambungan terputus. Periksa koneksi lalu coba lagi.';
                if (rejecting) setReasonError(error);
                else setActionError(error);
                return false;
            },
            onFinish: () => {
                submitting.current = false;
                setProcessing(false);
            },
        });
    }

    function renderSelection(row: PanelOvertimeRow) {
        return (
            <label className="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center">
                <input
                    type="checkbox"
                    className={checkboxStyle}
                    aria-label={`Pilih ${row.number}`}
                    disabled={processing}
                    checked={selectedIds.includes(row.id)}
                    onChange={(event) =>
                        setSelected(
                            event.target.checked
                                ? [...selectedIds, row.id]
                                : selectedIds.filter((id) => id !== row.id),
                        )
                    }
                />
            </label>
        );
    }

    function renderActions(row: PanelOvertimeRow) {
        return (
            <div className="flex flex-wrap gap-2">
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
                <Button
                    variant="secondary"
                    size="lg"
                    disabled={processing}
                    aria-label={`Setujui ${row.number}`}
                    onClick={() => submit(`${row.id}/approve`)}
                >
                    Setujui
                </Button>
                <Button
                    variant="danger"
                    size="lg"
                    disabled={processing}
                    aria-label={`Tolak ${row.number}`}
                    onClick={(event) => {
                        rejectTrigger.current = event.currentTarget;
                        setReasonError(undefined);
                        setRejecting(row);
                    }}
                >
                    Tolak
                </Button>
            </div>
        );
    }

    return (
        <section
            className="grid min-w-0 gap-4"
            aria-labelledby="approval-heading"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2
                    id="approval-heading"
                    ref={heading}
                    tabIndex={-1}
                    className="text-lg font-semibold text-ink"
                >
                    Menunggu persetujuan
                </h2>
                <p className="text-sm text-ink-muted">
                    {queue.total} pengajuan
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

            {queue.data.length === 0 ? (
                <p className="rounded-md border border-line bg-surface p-4 text-sm text-ink-muted">
                    Belum ada pengajuan yang menunggu persetujuan.
                </p>
            ) : (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-md border border-line bg-surface px-3 py-2">
                        <label className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-ink">
                            <input
                                type="checkbox"
                                className={checkboxStyle}
                                disabled={processing}
                                checked={allSelected}
                                ref={(input) => {
                                    if (input)
                                        input.indeterminate =
                                            selectedIds.length > 0 &&
                                            !allSelected;
                                }}
                                onChange={() =>
                                    setSelected(allSelected ? [] : visibleIds)
                                }
                            />
                            Pilih semua di halaman ini
                        </label>
                        <div className="flex flex-wrap items-center gap-3">
                            <p className="text-sm text-ink-muted" role="status">
                                {selectedIds.length} dipilih
                            </p>
                            <Button
                                size="lg"
                                disabled={
                                    processing || selectedIds.length === 0
                                }
                                onClick={() =>
                                    submit('bulk-approve', { ids: selectedIds })
                                }
                            >
                                Setujui terpilih
                            </Button>
                        </div>
                    </div>

                    <OvertimeList
                        rows={queue.data}
                        label="Antrean persetujuan"
                        renderSelection={renderSelection}
                        renderActions={renderActions}
                    />
                </>
            )}

            <PanelPagination
                page={queue}
                label="Halaman persetujuan"
                busy={processing}
            />

            {rejecting && (
                <OvertimeReasonDialog
                    row={rejecting}
                    busy={processing}
                    serverError={reasonError}
                    onSubmit={(reason) =>
                        submit(`${rejecting.id}/reject`, { reason })
                    }
                    onClose={() => setRejecting(null)}
                    restoreFocus={() => {
                        const trigger = rejectTrigger.current;
                        if (trigger?.isConnected && !trigger.disabled)
                            trigger.focus();
                        else heading.current?.focus();
                    }}
                />
            )}
        </section>
    );
}

export default function Approvals({
    queue,
    errors,
}: {
    queue: ApprovalQueue;
    errors?: { status?: string };
}) {
    return (
        <ApprovalPage
            key={queue.current_page}
            queue={queue}
            statusError={errors?.status}
        />
    );
}

Approvals.layout = [PanelLayout, { title: 'Persetujuan' }];
