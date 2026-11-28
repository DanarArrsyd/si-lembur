import { Link, useForm } from '@inertiajs/react';
import { clsx } from 'clsx';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { TimePicker } from '@/components/public/time-picker';
import { Typeahead } from '@/components/public/typeahead';
import { Button, buttonVariants } from '@/components/ui/button';
import PanelLayout from '@/layouts/panel-layout';
import { calculateOvertime, formatDuration, formatHours } from '@/lib/overtime';
import { breakOptions, timeOptions } from '@/lib/time-options';
import type { EmployeeOption } from '@/types/public';

type OvertimeFields = {
    overtime_date: string;
    start_time: string;
    end_time: string;
    break_minutes: number;
    description: string;
};

type Props = {
    dateBounds: { min: string; max: string };
    serverTime: string;
    maxGrossMinutes: number;
} & (
    | {
          overtime?: undefined;
          fixedDepartmentId: number | null;
          departments: {
              id: number;
              name: string;
              employees: EmployeeOption[];
          }[];
      }
    | {
          overtime: OvertimeFields & {
              id: number;
              number: string;
              department: string;
              employee: Pick<EmployeeOption, 'name' | 'nik'>;
          };
          fixedDepartmentId?: undefined;
          departments?: undefined;
      }
);

const times = timeOptions();
const breaks = breakOptions();
const historyUrl = '/panel/overtimes';
const control =
    'w-full min-w-0 rounded-sm border border-line-control bg-surface px-3 text-base text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary read-only:bg-surface-sunken aria-invalid:border-danger aria-invalid:bg-danger-soft';
const labelStyle = 'text-[15px] font-semibold text-ink';

function FieldError({ field, message }: { field: string; message?: string }) {
    return (
        message && (
            <p
                id={field + '-error'}
                className="text-sm text-danger"
                role="alert"
            >
                {message}
            </p>
        )
    );
}

function OvertimeFormPage(props: Props) {
    const { dateBounds, serverTime, maxGrossMinutes, overtime } = props;
    const form = useForm({
        department_id: props.fixedDepartmentId ?? null,
        employee_id: null as number | null,
        overtime_date: overtime?.overtime_date ?? dateBounds.max,
        start_time: overtime?.start_time ?? '',
        end_time: overtime?.end_time ?? '',
        break_minutes: overtime?.break_minutes ?? 0,
        description: overtime?.description ?? '',
    });
    const [employee, setEmployee] = useState<EmployeeOption | null>(null);
    const [networkError, setNetworkError] = useState('');
    const { data, errors } = form;
    const department = props.departments?.find(
        ({ id }) => id === data.department_id,
    );
    const isFixed = props.fixedDepartmentId != null;
    const calculation =
        data.start_time && data.end_time
            ? calculateOvertime(
                  data.start_time,
                  data.end_time,
                  data.break_minutes,
                  maxGrossMinutes,
              )
            : null;

    function change<K extends keyof typeof data>(
        field: K,
        value: (typeof data)[K],
    ) {
        form.setData((previous) => ({ ...previous, [field]: value }));
        form.clearErrors(field);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (form.processing) return;

        const formElement = event.currentTarget;
        setNetworkError('');
        const options = {
            onError: () => {
                requestAnimationFrame(() =>
                    formElement
                        .querySelector<HTMLElement>('[aria-invalid="true"]')
                        ?.focus(),
                );
            },
            onNetworkError: () => {
                setNetworkError(
                    'Sambungan terputus. Periksa koneksi lalu coba lagi.',
                );
                return false;
            },
        };
        if (overtime) {
            form.transform(
                ({
                    department_id: _department,
                    employee_id: _employee,
                    ...fields
                }) => fields,
            );
            form.patch(historyUrl + '/' + overtime.id, options);
        } else {
            form.post(historyUrl, options);
        }
    }

    return (
        <section aria-labelledby="overtime-form-title" className="max-w-2xl">
            <h2
                id="overtime-form-title"
                className="text-lg font-semibold text-ink"
            >
                {overtime ? 'Edit ' + overtime.number : 'Input atas nama'}
            </h2>
            <p className="mt-1 text-sm text-ink-muted">
                {overtime
                    ? 'Setelah perubahan disimpan, pengajuan tetap Menunggu persetujuan.'
                    : 'Lembur yang dicatat di sini langsung berstatus Disetujui.'}
            </p>
            <form
                noValidate
                onSubmit={submit}
                className="mt-4 grid min-w-0 gap-4 rounded-md border border-line bg-surface p-4 sm:p-6"
            >
                <fieldset
                    disabled={form.processing}
                    className="grid min-w-0 gap-4"
                >
                    <legend className="sr-only">Data lembur</legend>
                    <div className="grid min-w-0 gap-1.5">
                        <label htmlFor="department_id" className={labelStyle}>
                            Departemen
                        </label>
                        {overtime || isFixed ? (
                            <input
                                id="department_id"
                                className={clsx(control, 'h-[52px]')}
                                readOnly
                                value={
                                    overtime?.department ??
                                    department?.name ??
                                    'Departemen tidak aktif'
                                }
                            />
                        ) : (
                            <select
                                id="department_id"
                                className={clsx(
                                    control,
                                    'h-[52px] read-only:bg-surface',
                                )}
                                value={data.department_id ?? ''}
                                aria-invalid={!!errors.department_id}
                                aria-describedby={
                                    errors.department_id
                                        ? 'department_id-error'
                                        : undefined
                                }
                                onChange={(event) => {
                                    form.setData({
                                        ...data,
                                        department_id:
                                            Number(event.target.value) || null,
                                        employee_id: null,
                                    });
                                    setEmployee(null);
                                    form.clearErrors(
                                        'department_id',
                                        'employee_id',
                                    );
                                }}
                            >
                                <option value="">Pilih departemen</option>
                                {props.departments?.map(({ id, name }) => (
                                    <option key={id} value={id}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <FieldError
                            field="department_id"
                            message={errors.department_id}
                        />
                    </div>
                    {overtime ? (
                        <div className="grid min-w-0 gap-1.5">
                            <label
                                htmlFor="overtime-employee"
                                className={labelStyle}
                            >
                                Nama
                            </label>
                            <input
                                id="overtime-employee"
                                className={clsx(control, 'h-[52px]')}
                                readOnly
                                value={
                                    overtime.employee.name +
                                    ' · NIK ' +
                                    overtime.employee.nik
                                }
                            />
                        </div>
                    ) : (
                        <>
                            <Typeahead
                                key={department?.id ?? 'no-department'}
                                departmentId={department?.id ?? null}
                                employees={department?.employees ?? []}
                                value={employee}
                                onChange={(value) => {
                                    setEmployee(value);
                                    change('employee_id', value?.id ?? null);
                                }}
                                error={errors.employee_id}
                            />
                            {department?.employees.length === 0 && (
                                <p className="text-sm text-ink-muted">
                                    Tidak ada karyawan aktif di departemen ini.
                                </p>
                            )}
                        </>
                    )}
                    <div className="grid min-w-0 gap-1.5">
                        <label htmlFor="overtime_date" className={labelStyle}>
                            Tanggal lembur
                        </label>
                        <input
                            type="date"
                            id="overtime_date"
                            min={dateBounds.min}
                            max={dateBounds.max}
                            value={data.overtime_date}
                            aria-invalid={!!errors.overtime_date}
                            aria-describedby={
                                'overtime-date-help' +
                                (errors.overtime_date
                                    ? ' overtime_date-error'
                                    : '')
                            }
                            className={clsx(control, 'h-[52px]')}
                            onChange={(event) =>
                                change('overtime_date', event.target.value)
                            }
                        />
                        <p
                            id="overtime-date-help"
                            className="text-sm text-ink-muted"
                        >
                            Pilih tanggal mulai lembur, dari awal bulan lalu
                            hingga hari ini.
                        </p>
                        <FieldError
                            field="overtime_date"
                            message={errors.overtime_date}
                        />
                    </div>
                    <div className="grid min-w-0 gap-4 sm:grid-cols-2">
                        <TimePicker
                            id="start_time"
                            label="Jam mulai"
                            value={data.start_time || null}
                            options={times}
                            anchor={serverTime}
                            error={errors.start_time}
                            onChange={(value) => change('start_time', value)}
                        />
                        <TimePicker
                            id="end_time"
                            label="Jam selesai"
                            value={data.end_time || null}
                            options={times}
                            anchor={serverTime}
                            error={errors.end_time}
                            onChange={(value) => change('end_time', value)}
                        />
                    </div>
                    <TimePicker
                        id="break_minutes"
                        label="Istirahat"
                        value={data.break_minutes}
                        options={breaks}
                        error={errors.break_minutes}
                        onChange={(value) => change('break_minutes', value)}
                    />
                    <div
                        className="grid gap-1 rounded-md bg-primary-soft px-4 py-3 text-ink"
                        role="status"
                    >
                        <p className={labelStyle}>Total lembur</p>
                        {calculation?.ok ? (
                            <>
                                <p className="flex flex-wrap items-baseline gap-x-2 font-mono tabular-nums">
                                    <span className="text-xl font-semibold text-primary">
                                        {formatDuration(
                                            calculation.totalMinutes,
                                        )}
                                    </span>
                                    <span>
                                        ({formatHours(calculation.totalMinutes)}
                                        )
                                    </span>
                                </p>
                                {calculation.crossesMidnight && (
                                    <p className="text-sm">
                                        Jam selesai jatuh pada hari berikutnya.
                                    </p>
                                )}
                            </>
                        ) : (
                            <p
                                className={clsx(
                                    'text-sm',
                                    calculation
                                        ? 'text-danger'
                                        : 'text-ink-muted',
                                )}
                            >
                                {calculation?.message ??
                                    'Total tampil setelah jam mulai dan selesai dipilih.'}
                            </p>
                        )}
                    </div>
                    <div className="grid min-w-0 gap-1.5">
                        <label htmlFor="description" className={labelStyle}>
                            Kegiatan lembur
                        </label>
                        <textarea
                            id="description"
                            rows={3}
                            maxLength={500}
                            value={data.description}
                            aria-invalid={!!errors.description}
                            aria-describedby={
                                errors.description
                                    ? 'description-error'
                                    : undefined
                            }
                            className={clsx(control, 'resize-y py-2')}
                            onChange={(event) =>
                                change('description', event.target.value)
                            }
                        />
                        <FieldError
                            field="description"
                            message={errors.description}
                        />
                    </div>
                </fieldset>
                {networkError && (
                    <p role="alert" className="text-sm text-danger">
                        {networkError}
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        size="lg"
                        disabled={form.processing || (isFixed && !department)}
                    >
                        {form.processing
                            ? 'Menyimpan…'
                            : overtime
                              ? 'Simpan perubahan'
                              : 'Catat dan setujui'}
                    </Button>
                    <Link
                        href={historyUrl}
                        className={buttonVariants({
                            variant: 'secondary',
                            size: 'lg',
                        })}
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </section>
    );
}

OvertimeFormPage.layout = [PanelLayout, { title: 'Form lembur' }];

export default OvertimeFormPage;
