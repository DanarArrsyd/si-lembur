import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { DateChoiceField } from '@/components/public/date-choice';
import { ReviewCard } from '@/components/public/review-card';
import { TimePicker } from '@/components/public/time-picker';
import { TotalCard } from '@/components/public/total-card';
import { Typeahead } from '@/components/public/typeahead';
import { useEmployeeLookup } from '@/hooks/use-employee-lookup';
import PublicLayout from '@/layouts/public-layout';
import type { Employee } from '@/lib/employee-search';
import { calculateOvertime } from '@/lib/overtime';
import { breakOptions, timeOptions } from '@/lib/time-options';
import { review as reviewRoute, store as submitRoute } from '@/routes/public';
import type { DateChoice, OvertimeReview } from '@/types/public-overtime';

type Props = {
    departments: { id: number; name: string }[];
    dateChoices: DateChoice[];
    serverTime: string;
    maxGrossMinutes: number;
};

type Draft = {
    department_id: number | null;
    employee_id: number | null;
    date_choice: DateChoice['value'] | null;
    overtime_date: string | null;
    start_time: string | null;
    end_time: string | null;
    break_minutes: number;
    description: string;
    website: string;
};

const labels = {
    department_id: 'Departemen',
    employee_id: 'Nama',
    date_choice: 'Tanggal lembur',
    start_time: 'Jam mulai',
    end_time: 'Jam selesai',
    break_minutes: 'Istirahat',
    description: 'Kegiatan lembur',
} as const;

type Field = keyof typeof labels;

const fields = Object.keys(labels) as Field[];
const times = timeOptions();
const breaks = breakOptions();
const emptyEmployees: Employee[] = [];
const controlClass =
    'w-full rounded-sm border border-line-control bg-surface px-3 text-[17px] text-ink aria-invalid:border-danger aria-invalid:bg-danger-soft';

function fieldId(field: Field) {
    return `overtime-${field}`;
}

function fieldError(field: Field, message?: string) {
    return (
        message && (
            <p
                id={`${fieldId(field)}-error`}
                className="mt-1 text-sm text-danger"
            >
                {message}
            </p>
        )
    );
}

export default function OvertimeForm({
    departments,
    dateChoices,
    serverTime,
    maxGrossMinutes,
}: Props) {
    const initialDate = dateChoices[0];
    const form = useForm<Draft>({
        department_id: null,
        employee_id: null,
        date_choice: initialDate?.value ?? null,
        overtime_date: initialDate?.date ?? null,
        start_time: null,
        end_time: null,
        break_minutes: 0,
        description: '',
        website: '',
    });
    const [review, setReview] = useState<OvertimeReview | null>(null);
    const [requestError, setRequestError] = useState('');
    const heading = useRef<HTMLHeadingElement>(null);
    const editing = useRef(false);
    const requestInFlight = useRef(false);
    const { data, processing } = form;
    const lookup = useEmployeeLookup(data.department_id);
    const selectedDate = dateChoices.find(
        ({ value, date }) =>
            value === data.date_choice && date === data.overtime_date,
    );
    const calculation =
        data.start_time && data.end_time
            ? calculateOvertime(
                  data.start_time,
                  data.end_time,
                  data.break_minutes,
                  maxGrossMinutes,
              )
            : null;
    const errors = {
        ...form.errors,
        date_choice: form.errors.overtime_date ?? form.errors.date_choice,
    };

    if (calculation && !calculation.ok) {
        const field = calculation.rule === 'R3' ? 'break_minutes' : 'end_time';
        errors[field] = calculation.message;
    }

    const invalidFields = fields.filter((field) => errors[field]);

    useEffect(() => {
        if (review || editing.current) {
            heading.current?.focus();
            editing.current = false;
        }
    }, [review]);

    function change<FieldName extends keyof Draft>(
        field: FieldName,
        value: Draft[FieldName],
    ) {
        form.setData((current) => ({ ...current, [field]: value }));
        form.clearErrors(field);
        if (
            field === 'start_time' ||
            field === 'end_time' ||
            field === 'break_minutes'
        ) {
            form.clearErrors('start_time', 'end_time', 'break_minutes');
        }
    }

    function focusErrors(invalid: Field[]) {
        const target =
            invalid.length > 1
                ? 'overtime-errors'
                : invalid[0] && fieldId(invalid[0]);
        if (target) {
            requestAnimationFrame(() =>
                document.getElementById(target)?.focus(),
            );
        }
    }

    function handleReview(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (processing) return;

        setRequestError('');
        const missing: Partial<Record<Field, string>> = {};
        if (!data.department_id) missing.department_id = 'Pilih departemen.';
        if (!data.employee_id) missing.employee_id = 'Pilih nama dari daftar.';
        if (!selectedDate)
            missing.date_choice = errors.date_choice || 'Pilih tanggal lembur.';
        if (!data.start_time) missing.start_time = 'Pilih jam mulai.';
        if (!data.end_time) missing.end_time = 'Pilih jam selesai.';
        // PHP counts Unicode code points with mb_strlen.
        const descriptionLength = Array.from(data.description.trim()).length;
        if (descriptionLength < 5)
            missing.description = 'Tulis kegiatan lembur minimal 5 huruf.';
        if (descriptionLength > 500)
            missing.description = 'Kegiatan lembur maksimal 500 huruf.';
        if (calculation && !calculation.ok) {
            missing[calculation.rule === 'R3' ? 'break_minutes' : 'end_time'] =
                calculation.message;
        }
        const invalid = fields.filter((field) => missing[field]);
        if (invalid.length) {
            form.setError(missing);
            focusErrors(invalid);
            return;
        }

        postOvertime('review');
    }

    function postOvertime(action: 'review' | 'submit') {
        if (requestInFlight.current) return;

        requestInFlight.current = true;
        setRequestError('');

        const sending = action === 'submit';
        form.post(sending ? submitRoute.url() : reviewRoute.url(), {
            preserveState: sending ? 'errors' : true,
            preserveScroll: 'errors',
            onFlash: ({ review: nextReview }) => {
                if (nextReview) setReview(nextReview);
            },
            onError: (serverErrors) => {
                if (sending) setReview(null);
                focusErrors(
                    fields.filter(
                        (field) =>
                            serverErrors[field] ||
                            (field === 'date_choice' &&
                                serverErrors.overtime_date),
                    ),
                );
            },
            onHttpException: (response) => {
                setRequestError(
                    response.status === 429
                        ? 'Terlalu banyak percobaan. Tunggu sebentar, lalu coba lagi.'
                        : sending
                          ? 'Pengiriman belum terkonfirmasi. Coba lagi.'
                          : 'Pemeriksaan belum berhasil. Coba lagi.',
                );
                return false;
            },
            onNetworkError: () => {
                setRequestError(
                    'Koneksi terputus. Coba lagi setelah tersambung.',
                );
                return false;
            },
            onFinish: () => {
                requestInFlight.current = false;
            },
        });
    }

    if (review) {
        return (
            <ReviewCard
                review={review}
                headingRef={heading}
                processing={processing}
                requestError={requestError}
                onSubmit={() => postOvertime('submit')}
                onEdit={() => {
                    editing.current = true;
                    setRequestError('');
                    setReview(null);
                }}
            />
        );
    }

    return (
        <form onSubmit={handleReview} noValidate>
            <h1
                ref={heading}
                tabIndex={-1}
                className="mb-5 text-[22px] leading-[30px] font-bold"
            >
                Isi lembur
            </h1>

            <fieldset disabled={processing} className="grid min-w-0 gap-5">
                <div>
                    <label
                        htmlFor={fieldId('department_id')}
                        className="mb-2 block text-[15px] font-semibold"
                    >
                        Departemen
                    </label>
                    <select
                        id={fieldId('department_id')}
                        value={data.department_id ?? ''}
                        aria-invalid={errors.department_id ? true : undefined}
                        aria-describedby={
                            errors.department_id
                                ? `${fieldId('department_id')}-error`
                                : undefined
                        }
                        className={`h-[52px] ${controlClass}`}
                        onChange={(event) => {
                            form.setData({
                                ...data,
                                department_id:
                                    Number(event.currentTarget.value) || null,
                                employee_id: null,
                            });
                            form.clearErrors('department_id', 'employee_id');
                        }}
                    >
                        <option value="">Pilih departemen</option>
                        {departments.map(({ id, name }) => (
                            <option key={id} value={id}>
                                {name}
                            </option>
                        ))}
                    </select>
                    {fieldError('department_id', errors.department_id)}
                </div>

                <div>
                    <Typeahead
                        id={fieldId('employee_id')}
                        departmentId={data.department_id}
                        employees={lookup.employees ?? emptyEmployees}
                        unavailable={lookup.unavailable}
                        value={data.employee_id}
                        onChange={(value) => change('employee_id', value)}
                        error={errors.employee_id}
                    />
                    {lookup.failed ? (
                        <div className="mt-1 text-sm">
                            <p role="status" className="text-danger">
                                Daftar nama gagal dimuat.
                            </p>
                            <button
                                type="button"
                                onClick={lookup.retry}
                                className="min-h-12 rounded-sm text-primary underline"
                            >
                                Coba lagi
                            </button>
                        </div>
                    ) : (
                        lookup.unavailable && (
                            <p
                                role="status"
                                className="mt-1 text-sm text-ink-muted"
                            >
                                Memuat daftar nama…
                            </p>
                        )
                    )}
                </div>

                <DateChoiceField
                    id={fieldId('date_choice')}
                    choices={dateChoices}
                    value={selectedDate?.value}
                    error={errors.date_choice}
                    onChange={(choice) => {
                        form.setData((current) => ({
                            ...current,
                            date_choice: choice.value,
                            overtime_date: choice.date,
                        }));
                        form.clearErrors('date_choice', 'overtime_date');
                    }}
                />

                <TimePicker
                    id={fieldId('start_time')}
                    label={labels.start_time}
                    value={data.start_time}
                    options={times}
                    anchor={serverTime}
                    error={errors.start_time}
                    onChange={(value) => change('start_time', value)}
                />
                <TimePicker
                    id={fieldId('end_time')}
                    label={labels.end_time}
                    value={data.end_time}
                    options={times}
                    anchor={serverTime}
                    error={errors.end_time}
                    onChange={(value) => change('end_time', value)}
                />
                <TimePicker
                    id={fieldId('break_minutes')}
                    label={labels.break_minutes}
                    value={data.break_minutes}
                    options={breaks}
                    error={errors.break_minutes}
                    onChange={(value) => change('break_minutes', value)}
                />
                <TotalCard
                    minutes={calculation?.ok ? calculation.totalMinutes : null}
                    invalid={calculation?.ok === false}
                />
                {calculation?.ok && calculation.crossesMidnight && (
                    <p className="-mt-3 text-sm text-ink-muted">
                        Jam selesai jatuh pada hari berikutnya.
                    </p>
                )}

                <div>
                    <label
                        htmlFor={fieldId('description')}
                        className="mb-2 block text-[15px] font-semibold"
                    >
                        Kegiatan lembur
                    </label>
                    <textarea
                        id={fieldId('description')}
                        value={data.description}
                        onChange={(event) =>
                            change('description', event.currentTarget.value)
                        }
                        aria-invalid={errors.description ? true : undefined}
                        aria-describedby={
                            errors.description
                                ? `${fieldId('description')}-error`
                                : 'overtime-description-help'
                        }
                        rows={3}
                        className={`min-h-24 resize-y py-2 ${controlClass}`}
                    />
                    {errors.description ? (
                        fieldError('description', errors.description)
                    ) : (
                        <p
                            id="overtime-description-help"
                            className="text-sm text-ink-muted"
                        >
                            Tulis kegiatan yang dilakukan, 5–500 karakter.
                        </p>
                    )}
                </div>

                <div className="sr-only" aria-hidden="true">
                    <label htmlFor="overtime-website">Situs web</label>
                    <input
                        id="overtime-website"
                        name="website"
                        tabIndex={-1}
                        autoComplete="off"
                        value={data.website}
                        onChange={(event) =>
                            change('website', event.currentTarget.value)
                        }
                    />
                </div>
            </fieldset>

            {invalidFields.length > 1 && (
                <section
                    id="overtime-errors"
                    aria-labelledby="overtime-errors-title"
                    tabIndex={-1}
                    className="mt-4 rounded-md border border-danger bg-danger-soft p-3 text-sm text-danger"
                >
                    <h2 id="overtime-errors-title" className="font-semibold">
                        Periksa isian
                    </h2>
                    <ul className="mt-2 space-y-2">
                        {invalidFields.map((field) => (
                            <li key={field}>
                                <a
                                    href={`#${fieldId(field)}`}
                                    className="underline"
                                >
                                    {labels[field]}: {errors[field]}
                                </a>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <div className="sticky bottom-0 z-10 -mx-4 mt-5 border-t border-line bg-surface px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                {requestError && (
                    <p role="alert" className="mb-2 text-sm text-danger">
                        {requestError}
                    </p>
                )}
                <button
                    type="submit"
                    disabled={processing}
                    className="h-[52px] w-full rounded-md bg-primary px-4 text-[17px] font-semibold text-on-primary hover:bg-primary-hover disabled:opacity-50"
                >
                    {processing ? 'Memeriksa…' : 'Lanjut periksa'}
                </button>
            </div>
        </form>
    );
}

OvertimeForm.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>;
