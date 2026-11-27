import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { StatusPill } from '@/components/public/status-pill';
import { Typeahead } from '@/components/public/typeahead';
import { useEmployeeLookup } from '@/hooks/use-employee-lookup';
import PublicLayout from '@/layouts/public-layout';
import {
    formatOvertimeDate,
    formatOvertimeEnd,
} from '@/lib/format-overtime-date';
import { formatDuration, formatHours } from '@/lib/overtime';
import type { PublicStatusProps, StatusEntry } from '@/types/public';

const textAction =
    'inline-flex min-h-12 w-fit items-center rounded-sm text-sm font-semibold text-primary underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

type StatusResult = {
    employeeId: number;
    entries: StatusEntry[];
    error?: string;
};

function HistoryCard({ entry }: { entry: StatusEntry }) {
    return (
        <article className="grid gap-3 rounded-md border border-line bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <h3 className="font-mono text-[17px] font-semibold text-ink tabular-nums">
                        {formatOvertimeDate(entry.date)}
                    </h3>
                    <p className="font-mono text-sm text-ink-muted tabular-nums">
                        {entry.number}
                    </p>
                </div>
                <StatusPill status={entry.status} />
            </div>
            <dl className="grid gap-2 text-[17px]">
                <div>
                    <dt className="text-[15px] font-semibold text-ink-muted">
                        Jam lembur
                    </dt>
                    <dd className="font-mono text-ink tabular-nums">
                        {entry.start.slice(11, 16)}–
                        {formatOvertimeEnd(entry.date, entry.end)}
                    </dd>
                </div>
                <div>
                    <dt className="text-[15px] font-semibold text-ink-muted">
                        Total lembur
                    </dt>
                    <dd className="font-mono text-ink tabular-nums">
                        {formatDuration(entry.total_minutes)} (
                        {formatHours(entry.total_minutes)})
                    </dd>
                </div>
                {entry.reject_reason && (
                    <div>
                        <dt className="text-[15px] font-semibold text-ink-muted">
                            Alasan penolakan
                        </dt>
                        <dd className="break-words whitespace-pre-wrap text-ink">
                            {entry.reject_reason}
                        </dd>
                    </div>
                )}
            </dl>
            {entry.status === 'REJECTED' &&
                (entry.can_resubmit ? (
                    <Link href="/" className={textAction}>
                        Ajukan ulang lewat form lembur.
                    </Link>
                ) : (
                    <p className="text-sm text-ink-muted">
                        Hubungi supervisor untuk mengajukan ulang lembur ini.
                    </p>
                ))}
        </article>
    );
}

export default function StatusCheck({
    departments,
    prefillDepartmentId,
    prefillEmployee,
}: PublicStatusProps) {
    const [department, setDepartment] = useState(prefillDepartmentId);
    const [employee, setEmployee] = useState(prefillEmployee);
    const [result, setResult] = useState<StatusResult | null>(null);
    const [attempt, setAttempt] = useState(0);
    const lookup = useEmployeeLookup(department);
    const employeeId = employee?.id ?? null;

    useEffect(() => {
        if (employeeId === null) return;
        const controller = new AbortController();
        setResult(null);

        void (async () => {
            try {
                const response = await fetch(
                    `/api/status?employee_id=${employeeId}`,
                    { signal: controller.signal },
                );
                if (!response.ok) throw new Error('Status lookup failed');
                const entries = (await response.json()) as StatusEntry[];
                if (!controller.signal.aborted)
                    setResult({ employeeId, entries });
            } catch {
                if (!controller.signal.aborted) {
                    setResult({
                        employeeId,
                        entries: [],
                        error: 'Status pengajuan gagal dimuat.',
                    });
                }
            }
        })();

        return () => controller.abort();
    }, [employeeId, attempt]);

    const current = result?.employeeId === employeeId ? result : null;

    return (
        <div className="grid gap-4">
            <h1 className="text-xl font-semibold text-ink">
                Cek status pengajuan
            </h1>
            <div className="grid gap-1.5">
                <label
                    htmlFor="status-department"
                    className="text-[15px] font-semibold text-ink"
                >
                    Departemen
                </label>
                <select
                    id="status-department"
                    value={department ?? ''}
                    onChange={(event) => {
                        setDepartment(
                            event.target.value
                                ? Number(event.target.value)
                                : null,
                        );
                        setEmployee(null);
                        setResult(null);
                    }}
                    className="h-[52px] w-full rounded-sm border border-line-control bg-surface px-3 text-[17px] text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <option value="">Pilih departemen</option>
                    {departments.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-1.5">
                <Typeahead
                    departmentId={department}
                    employees={lookup.employees}
                    loading={lookup.loading}
                    value={employee}
                    onChange={setEmployee}
                />
                {lookup.error && (
                    <div className="flex flex-wrap items-center gap-x-3">
                        <p role="status" className="text-sm text-danger">
                            {lookup.error}
                        </p>
                        <button
                            type="button"
                            className={textAction}
                            onClick={lookup.reload}
                        >
                            Muat ulang daftar
                        </button>
                    </div>
                )}
            </div>

            {employeeId !== null && (
                <section
                    aria-labelledby="status-results"
                    className="grid gap-3"
                >
                    <h2 id="status-results" className="font-semibold text-ink">
                        Pengajuan 60 hari terakhir
                    </h2>
                    {current === null ? (
                        <p role="status" className="text-[17px] text-ink-muted">
                            Memuat status pengajuan…
                        </p>
                    ) : current.error ? (
                        <div className="flex flex-wrap items-center gap-x-3">
                            <p role="alert" className="text-sm text-danger">
                                {current.error}
                            </p>
                            <button
                                type="button"
                                className={textAction}
                                onClick={() => {
                                    setResult(null);
                                    setAttempt((count) => count + 1);
                                }}
                            >
                                Muat ulang status
                            </button>
                        </div>
                    ) : current.entries.length === 0 ? (
                        <p
                            role="status"
                            className="rounded-md border border-line bg-surface p-4 text-[17px] text-ink-muted"
                        >
                            Belum ada pengajuan dalam 60 hari terakhir.
                        </p>
                    ) : (
                        <ul
                            aria-label="Daftar pengajuan"
                            className="grid gap-3"
                        >
                            {current.entries.map((entry) => (
                                <li key={entry.number}>
                                    <HistoryCard entry={entry} />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            )}
        </div>
    );
}

StatusCheck.layout = [
    PublicLayout,
    { title: 'Cek status pengajuan', showFormLink: true },
];
