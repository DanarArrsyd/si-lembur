import { clsx } from 'clsx';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { searchEmployees } from '@/lib/employee-search';
import type { EmployeeOption } from '@/types/public';

type Props = {
    /** Departemen terpilih; null = belum dipilih (input nonaktif). */
    departmentId: number | null;
    /** Karyawan aktif departemen itu, dimuat sekali dari /api/departments/{id}/employees. */
    employees: EmployeeOption[];
    loading?: boolean;
    value: EmployeeOption | null;
    onChange: (value: EmployeeOption | null) => void;
    error?: string;
};

/**
 * Field "Nama" form publik: ARIA 1.2 combobox dengan listbox (PRD §4.1, DESIGN.md §4, §6).
 *
 * Teks yang diketik bukan pilihan: nilai baru terisi saat nama dipilih dari daftar
 * (klik/tap, atau ↓/↑ lalu Enter). Mengetik lagi setelah memilih mengosongkan nilai.
 * Ganti departemen → nilai dan teks dikosongkan.
 */
export function Typeahead({
    departmentId,
    employees,
    loading = false,
    value,
    onChange,
    error,
}: Props) {
    const id = useId();
    const inputId = `${id}-input`;
    const listId = `${id}-list`;
    const helpId = `${id}-help`;
    const errorId = `${id}-error`;

    const [query, setQuery] = useState(value?.name ?? '');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);

    const results = useMemo(
        () => searchEmployees(employees, query),
        [employees, query],
    );

    // Ganti departemen: nama dari departemen lama tidak berlaku lagi.
    const previousDepartment = useRef(departmentId);
    useEffect(() => {
        if (previousDepartment.current === departmentId) {
            return;
        }

        previousDepartment.current = departmentId;
        setQuery('');
        setOpen(false);
        setActive(-1);

        if (value !== null) {
            onChange(null);
        }
    }, [departmentId, value, onChange]);

    const disabled = departmentId === null || loading;
    const showList = open && query.trim() !== '' && !disabled;

    function choose(employee: EmployeeOption) {
        setQuery(employee.name);
        setOpen(false);
        setActive(-1);
        onChange(employee);
    }

    function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setOpen(true);
                setActive((i) => Math.min(i + 1, results.length - 1));
                break;
            case 'ArrowUp':
                event.preventDefault();
                setActive((i) => Math.max(i - 1, 0));
                break;
            case 'Enter':
                if (showList) {
                    // Jangan mengirim form saat daftar terbuka.
                    event.preventDefault();

                    if (active >= 0 && results[active]) {
                        choose(results[active]);
                    }
                }
                break;
            case 'Escape':
                if (showList) {
                    event.preventDefault();
                    setOpen(false);
                    setActive(-1);
                }
                break;
        }
    }

    const helper =
        departmentId === null
            ? 'Pilih departemen dulu.'
            : loading
              ? 'Memuat daftar nama…'
              : value
                ? null
                : 'Ketik nama, lalu pilih dari daftar.';

    const optionId = (index: number) => `${id}-option-${index}`;

    return (
        <div className="relative flex flex-col gap-1.5">
            <label
                htmlFor={inputId}
                className="text-[15px] leading-[22px] font-semibold text-ink"
            >
                Nama
            </label>

            <input
                id={inputId}
                type="text"
                role="combobox"
                autoComplete="off"
                autoCapitalize="words"
                spellCheck={false}
                enterKeyHint="done"
                aria-autocomplete="list"
                aria-expanded={showList}
                aria-controls={listId}
                aria-activedescendant={
                    showList && active >= 0 ? optionId(active) : undefined
                }
                aria-invalid={error ? true : undefined}
                aria-describedby={clsx(helpId, error && errorId)}
                disabled={disabled}
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                    setActive(-1);

                    if (value !== null) {
                        onChange(null);
                    }
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                onKeyDown={handleKeyDown}
                className={clsx(
                    'h-[52px] w-full rounded-sm border px-3 text-[17px] text-ink outline-none',
                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
                    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-muted',
                    error
                        ? 'border-danger bg-danger-soft'
                        : 'border-line-control bg-surface',
                )}
            />

            {showList && results.length > 0 && (
                <ul
                    id={listId}
                    role="listbox"
                    aria-label="Nama karyawan"
                    className="absolute top-[calc(100%+4px)] right-0 left-0 z-20 max-h-[min(60svh,360px)] overflow-y-auto rounded-md border border-line bg-surface py-1 shadow-[var(--shadow-float)]"
                >
                    {results.map((employee, index) => (
                        <li
                            key={employee.id}
                            id={optionId(index)}
                            role="option"
                            aria-selected={index === active}
                            // mousedown sebelum blur input, supaya daftar tidak tertutup duluan.
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => choose(employee)}
                            className={clsx(
                                'flex min-h-[52px] cursor-pointer flex-col justify-center px-3 py-1.5',
                                index === active
                                    ? 'bg-primary-soft'
                                    : 'hover:bg-surface-sunken',
                            )}
                        >
                            <span className="text-[17px] leading-6 font-semibold text-ink">
                                {employee.name}
                            </span>
                            <span className="font-mono text-sm font-medium text-ink-muted tabular-nums">
                                {employee.nik}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {showList && results.length === 0 && (
                <p
                    role="status"
                    className="absolute top-[calc(100%+4px)] right-0 left-0 z-20 rounded-md border border-line bg-surface px-3 py-3 text-[15px] text-ink-muted shadow-[var(--shadow-float)]"
                >
                    Nama tidak ditemukan. Pastikan departemen sudah benar.
                </p>
            )}

            <p id={helpId} className="text-sm text-ink-muted">
                {value ? (
                    <>
                        NIK{' '}
                        <span className="font-mono font-medium text-ink tabular-nums">
                            {value.nik}
                        </span>
                    </>
                ) : (
                    helper
                )}
            </p>

            {error && (
                <p id={errorId} className="text-sm font-medium text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}
