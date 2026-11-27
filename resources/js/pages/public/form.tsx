import { router } from '@inertiajs/react';
import clsx from 'clsx';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { primaryButton, ReviewCard } from '@/components/public/review-card';
import { TimePicker } from '@/components/public/time-picker';
import { Typeahead } from '@/components/public/typeahead';
import PublicLayout from '@/layouts/public-layout';
import { calculateOvertime, formatDuration, formatHours } from '@/lib/overtime';
import { breakOptions, timeOptions } from '@/lib/time-options';
import type {
    EmployeeOption,
    OvertimeReview,
    PublicOvertimeFormProps,
} from '@/types/public';

const hours = timeOptions();
const breaks = breakOptions();
const fieldLabel = 'block text-[15px] font-semibold text-ink';
const fieldControl =
    'w-full rounded-sm border px-3 text-[17px] text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
const fieldError = 'text-sm text-danger';
const focusRing =
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
const fields = [
    'department_id',
    'employee_id',
    'overtime_date',
    'start_time',
    'end_time',
    'break_minutes',
    'description',
] as const;
type Field = (typeof fields)[number];

function controlState(error?: string) {
    return error
        ? 'border-danger bg-danger-soft'
        : 'border-line-control bg-surface';
}

function fieldProps(id: Field, error?: string) {
    return {
        id,
        'aria-invalid': !!error,
        'aria-describedby': error ? `${id}-error` : undefined,
    };
}

function ErrorText({ id, message }: { id: Field; message?: string }) {
    return message ? (
        <p id={`${id}-error`} className={fieldError}>
            {message}
        </p>
    ) : null;
}

export default function OvertimeForm({
    departments,
    dateChoices,
    serverTime,
    maxGrossMinutes,
}: PublicOvertimeFormProps) {
    const [department, setDepartment] = useState<number | null>(null);
    const [employee, setEmployee] = useState<EmployeeOption | null>(null);
    const [selectedDate, setSelectedDate] = useState('');
    const [start, setStart] = useState<string | null>(null);
    const [end, setEnd] = useState<string | null>(null);
    const [rest, setRest] = useState(0);
    const [description, setDescription] = useState('');
    const [website, setWebsite] = useState('');
    const [errors, setErrors] = useState<Partial<Record<Field, string>>>({});
    const [review, setReview] = useState<OvertimeReview | null>(null);
    const [processing, setProcessing] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [lookup, setLookup] = useState<{
        department: number;
        employees: EmployeeOption[];
        error?: string;
    } | null>(null);
    const [lookupRetry, setLookupRetry] = useState(0);
    const cache = useRef(new Map<number, EmployeeOption[]>());

    useEffect(() => {
        if (department === null) return;
        const cached = cache.current.get(department);
        if (cached) {
            setLookup({ department, employees: cached });
            return;
        }

        const controller = new AbortController();
        void (async () => {
            try {
                const response = await fetch(
                    `/api/departments/${department}/employees`,
                    { signal: controller.signal },
                );
                if (!response.ok) throw new Error('Employee lookup failed');
                const employees = (await response.json()) as EmployeeOption[];
                if (!controller.signal.aborted) {
                    cache.current.set(department, employees);
                    setLookup({ department, employees });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setLookup({
                        department,
                        employees: [],
                        error: 'Daftar nama gagal dimuat.',
                    });
                }
            }
        })();
        return () => controller.abort();
    }, [department, lookupRetry]);

    const date = dateChoices.find((choice) => choice.date === selectedDate);
    const currentLookup = lookup?.department === department ? lookup : null;
    const calculation =
        start && end
            ? calculateOvertime(start, end, rest, maxGrossMinutes)
            : null;
    const invalidCalculation = calculation?.ok === false ? calculation : null;
    const invalidField =
        invalidCalculation?.rule === 'R3' ? 'break_minutes' : 'end_time';
    const fieldErrors = { ...errors };
    if (invalidCalculation)
        fieldErrors[invalidField] = invalidCalculation.message;
    const visibleErrors = fields.filter((field) => fieldErrors[field]);

    function clearError(field: Field) {
        setErrors((previous) => ({ ...previous, [field]: undefined }));
    }

    function changeTime(field: 'start_time' | 'end_time' | 'break_minutes') {
        setErrors((previous) => ({
            ...previous,
            [field]: undefined,
            end_time: undefined,
            break_minutes: undefined,
        }));
    }

    function preview(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) return;
        setRequestError('');
        const validation: Partial<Record<Field, string>> = {};
        if (!department) validation.department_id = 'Pilih departemen.';
        if (!employee) validation.employee_id = 'Pilih nama dari daftar.';
        if (!date) validation.overtime_date = 'Pilih tanggal lembur.';
        if (!start) validation.start_time = 'Pilih jam mulai.';
        if (!end) validation.end_time = 'Pilih jam selesai.';
        const length = Array.from(description.trim()).length;
        if (length < 5)
            validation.description = 'Tulis kegiatan lembur minimal 5 huruf.';
        if (length > 500)
            validation.description = 'Kegiatan lembur maksimal 500 huruf.';
        setErrors(validation);

        if (
            !department ||
            !employee ||
            !date ||
            !start ||
            !end ||
            invalidCalculation ||
            Object.keys(validation).length > 0
        )
            return;

        setProcessing(true);
        router.post(
            '/lembur/periksa',
            {
                department_id: department,
                employee_id: employee.id,
                date_choice: date.value,
                overtime_date: date.date,
                start_time: start,
                end_time: end,
                break_minutes: rest,
                description,
                website,
            },
            {
                preserveScroll: 'errors',
                onFlash: (flash) => {
                    if (flash.review) setReview(flash.review as OvertimeReview);
                },
                onError: (serverErrors) => {
                    setErrors({
                        ...serverErrors,
                        overtime_date:
                            serverErrors.overtime_date ??
                            serverErrors.date_choice,
                    });
                },
                onHttpException: (response) => {
                    if (response.status !== 429 && response.status !== 422)
                        return;
                    setRequestError(
                        response.status === 429
                            ? 'Terlalu banyak percobaan. Tunggu satu menit, lalu coba lagi.'
                            : 'Pengajuan tidak bisa diproses. Muat ulang halaman lalu coba lagi.',
                    );
                    return false;
                },
                onNetworkError: () => {
                    setRequestError(
                        'Sambungan terputus. Periksa koneksi lalu coba lagi.',
                    );
                    return false;
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    if (review)
        return <ReviewCard review={review} onEdit={() => setReview(null)} />;

    return (
        <form className="grid gap-4" onSubmit={preview} noValidate>
            <fieldset disabled={processing} className="grid min-w-0 gap-4">
                <div className="grid gap-1.5">
                    <label className={fieldLabel} htmlFor="department_id">
                        Departemen
                    </label>
                    <select
                        {...fieldProps('department_id', errors.department_id)}
                        className={clsx(
                            fieldControl,
                            'h-[52px]',
                            controlState(errors.department_id),
                        )}
                        value={department ?? ''}
                        onChange={(event) => {
                            setDepartment(
                                event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            );
                            setEmployee(null);
                            setLookup(null);
                            setErrors((previous) => ({
                                ...previous,
                                department_id: undefined,
                                employee_id: undefined,
                            }));
                        }}
                    >
                        <option value="">Pilih departemen</option>
                        {departments.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                    <ErrorText
                        id="department_id"
                        message={errors.department_id}
                    />
                </div>

                <div className="grid gap-1.5">
                    <Typeahead
                        id="employee_id"
                        departmentId={department}
                        employees={currentLookup?.employees ?? []}
                        loading={department !== null && currentLookup === null}
                        value={employee}
                        onChange={(next) => {
                            setEmployee(next);
                            clearError('employee_id');
                        }}
                        error={errors.employee_id}
                    />
                    {currentLookup?.error && (
                        <div className="flex items-center gap-3">
                            <p role="status" className={fieldError}>
                                {currentLookup.error}
                            </p>
                            <button
                                type="button"
                                className={clsx(
                                    focusRing,
                                    'min-h-12 px-2 text-sm font-semibold text-primary',
                                )}
                                onClick={() => {
                                    setLookup(null);
                                    setLookupRetry((count) => count + 1);
                                }}
                            >
                                Muat ulang daftar
                            </button>
                        </div>
                    )}
                </div>

                <fieldset
                    {...fieldProps('overtime_date', errors.overtime_date)}
                    tabIndex={-1}
                    className="grid min-w-0 gap-2"
                >
                    <legend className={clsx(fieldLabel, 'mb-1.5')}>
                        Tanggal lembur
                    </legend>
                    {dateChoices.map((choice) => (
                        <label
                            key={choice.date}
                            className={clsx(
                                'flex min-h-[52px] items-center gap-2.5 rounded-sm border px-3 text-[17px] text-ink',
                                controlState(errors.overtime_date),
                            )}
                        >
                            <input
                                type="radio"
                                name="date_choice"
                                value={choice.value}
                                checked={selectedDate === choice.date}
                                className={clsx(
                                    'size-5 shrink-0 accent-primary',
                                    focusRing,
                                )}
                                onChange={() => {
                                    setSelectedDate(choice.date);
                                    clearError('overtime_date');
                                }}
                            />
                            {choice.label}
                        </label>
                    ))}
                    <ErrorText
                        id="overtime_date"
                        message={errors.overtime_date}
                    />
                </fieldset>

                <TimePicker
                    id="start_time"
                    label="Jam mulai"
                    value={start}
                    options={hours}
                    anchor={serverTime}
                    error={fieldErrors.start_time}
                    onChange={(time) => {
                        setStart(time);
                        changeTime('start_time');
                    }}
                />
                <TimePicker
                    id="end_time"
                    label="Jam selesai"
                    value={end}
                    options={hours}
                    anchor={serverTime}
                    error={fieldErrors.end_time}
                    onChange={(time) => {
                        setEnd(time);
                        changeTime('end_time');
                    }}
                />
                <TimePicker
                    id="break_minutes"
                    label="Istirahat"
                    value={rest}
                    options={breaks}
                    error={fieldErrors.break_minutes}
                    onChange={(minutes) => {
                        setRest(minutes);
                        changeTime('break_minutes');
                    }}
                />

                <div
                    className="grid gap-1 rounded-md bg-primary-soft px-4 py-3 text-ink"
                    role="status"
                >
                    <p className={fieldLabel}>Total lembur</p>
                    {calculation?.ok ? (
                        <p className="flex flex-wrap items-baseline gap-x-2 font-mono tabular-nums">
                            <span className="text-[28px] font-semibold text-primary">
                                {formatDuration(calculation.totalMinutes)}
                            </span>
                            <span className="text-[17px]">
                                ({formatHours(calculation.totalMinutes)})
                            </span>
                        </p>
                    ) : (
                        <p className="text-[17px] text-ink-muted">
                            Isi jam mulai dan jam selesai.
                        </p>
                    )}
                </div>

                <div className="grid gap-1.5">
                    <label className={fieldLabel} htmlFor="description">
                        Kegiatan lembur
                    </label>
                    <textarea
                        {...fieldProps('description', errors.description)}
                        rows={3}
                        value={description}
                        className={clsx(
                            fieldControl,
                            'min-h-[84px] py-2.5',
                            controlState(errors.description),
                        )}
                        onChange={(event) => {
                            setDescription(event.target.value);
                            clearError('description');
                        }}
                    />
                    <ErrorText id="description" message={errors.description} />
                </div>

                <div hidden>
                    <label htmlFor="website">Website</label>
                    <input
                        id="website"
                        name="website"
                        tabIndex={-1}
                        autoComplete="off"
                        value={website}
                        onChange={(event) => setWebsite(event.target.value)}
                    />
                </div>
            </fieldset>
            {visibleErrors.length > 1 && (
                <div
                    className="rounded-md border border-danger bg-danger-soft p-3 text-sm text-danger"
                    role="alert"
                >
                    <p className="font-semibold">Periksa isian berikut.</p>
                    <ul className="mt-1 list-disc space-y-1 pl-5">
                        {visibleErrors.map((field) => (
                            <li key={field}>
                                <a
                                    href={`#${field}`}
                                    className={clsx(
                                        'underline underline-offset-2',
                                        focusRing,
                                    )}
                                    onClick={() =>
                                        document.getElementById(field)?.focus()
                                    }
                                >
                                    {fieldErrors[field]}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {requestError && (
                <p className={fieldError} role="alert">
                    {requestError}
                </p>
            )}
            <div className="sticky bottom-0 -mx-4 border-t border-line bg-canvas px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <button
                    type="submit"
                    disabled={processing}
                    className={primaryButton}
                >
                    {processing ? 'Memeriksa…' : 'Lanjut periksa'}
                </button>
            </div>
        </form>
    );
}

OvertimeForm.layout = [PublicLayout, { title: 'Input lembur' }];
