import { Link } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PanelOvertimeRow } from '@/types/panel-overtime';

export function OvertimeReasonDialog({
    row,
    action = 'reject',
    busy,
    serverError,
    onSubmit,
    onClose,
    restoreFocus,
}: {
    row: PanelOvertimeRow;
    action?: 'reject' | 'cancel-approval';
    busy: boolean;
    serverError?: string;
    onSubmit: (reason: string) => void;
    onClose: () => void;
    restoreFocus: () => void;
}) {
    const [reason, setReason] = useState('');
    const [localError, setLocalError] = useState('');
    const [edited, setEdited] = useState(false);
    const id = useId();
    const cancelling = action === 'cancel-approval';
    const title = cancelling ? 'Batalkan persetujuan' : 'Tolak pengajuan';
    const error = localError || (!edited ? serverError : undefined);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (busy) return;
        const value = reason.trim();
        const length = Array.from(value).length;
        if (length < 5 || length > 500) {
            setLocalError(
                length < 5
                    ? 'Tulis alasan minimal 5 huruf.'
                    : 'Alasan maksimal 500 karakter.',
            );
            return;
        }

        setLocalError('');
        setEdited(false);
        onSubmit(value);
    }

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !busy) onClose();
            }}
        >
            <DialogContent
                className="max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-md bg-surface shadow-[var(--shadow-float)] duration-150 motion-reduce:animate-none [&>button]:hidden"
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    restoreFocus();
                }}
                onEscapeKeyDown={(event) => {
                    if (busy) event.preventDefault();
                }}
                onPointerDownOutside={(event) => {
                    if (busy) event.preventDefault();
                }}
            >
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>
                    Pengajuan {row.number} atas nama {row.employee.name}.{' '}
                    {cancelling ? (
                        'Pengajuan kembali ke status Menunggu dan keluar dari rekap sampai disetujui lagi.'
                    ) : (
                        <>
                            Karyawan dapat mengajukan ulang jika tanggal lembur
                            masih tersedia di form.{' '}
                            <Link
                                href={'/panel/overtimes/' + row.id + '/edit'}
                                className="font-semibold text-primary-hover underline underline-offset-2"
                                aria-disabled={busy}
                                onClick={(event) => {
                                    if (busy) event.preventDefault();
                                }}
                            >
                                Edit pengajuan
                            </Link>{' '}
                            jika yang perlu diperbaiki hanya jam atau kegiatan.
                        </>
                    )}
                </DialogDescription>
                <form onSubmit={submit} className="grid gap-3" noValidate>
                    <div className="grid gap-2">
                        <label
                            htmlFor={id}
                            className="text-sm font-semibold text-ink"
                        >
                            {cancelling
                                ? 'Alasan pembatalan'
                                : 'Alasan penolakan'}
                        </label>
                        <p id={`${id}-help`} className="text-sm text-ink-muted">
                            {cancelling
                                ? 'Alasan pembatalan tersimpan di audit.'
                                : 'Alasan tampil di Cek status. Hindari informasi pribadi.'}
                        </p>
                        <textarea
                            id={id}
                            rows={3}
                            value={reason}
                            disabled={busy}
                            aria-invalid={!!error}
                            aria-describedby={
                                error ? `${id}-help ${id}-error` : `${id}-help`
                            }
                            onChange={(event) => {
                                setReason(event.target.value);
                                setLocalError('');
                                setEdited(true);
                            }}
                            className="min-h-24 w-full resize-y rounded-sm border border-line-control bg-surface px-3 py-2 text-base text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary aria-invalid:border-danger aria-invalid:bg-danger-soft"
                        />
                        {error && (
                            <p
                                id={`${id}-error`}
                                className="text-sm text-danger"
                                role="alert"
                            >
                                {error}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="lg"
                            disabled={busy}
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="danger"
                            size="lg"
                            disabled={busy}
                        >
                            {busy ? 'Mengirim…' : title}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
