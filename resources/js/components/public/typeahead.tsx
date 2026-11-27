import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { searchEmployees } from '@/lib/employee-search';
import type { Employee } from '@/lib/employee-search';

type Props = {
    id?: string;
    departmentId: number | null;
    employees: Employee[];
    value: number | null;
    onChange: (employeeId: number | null) => void;
    unavailable?: boolean;
    error?: string;
};

export function Typeahead({
    id: fieldId,
    departmentId,
    employees,
    value,
    onChange,
    unavailable = false,
    error,
}: Props) {
    const generatedId = useId();
    const id = fieldId ?? generatedId;
    const listId = `${id}-options`;
    const descriptionId = `${id}-description`;
    const previousDepartment = useRef(departmentId);
    const [query, setQuery] = useState<string | null>(null);
    const [open, setOpen] = useState(false);
    const [activeId, setActiveId] = useState<number | null>(null);
    const selected = employees.find((employee) => employee.id === value);
    const disabled = departmentId === null || unavailable;
    const options = searchEmployees(employees, query ?? '');
    const listOpen = open && !disabled && options.length > 0;
    const activeIndex = options.findIndex(
        (employee) => employee.id === activeId,
    );
    const activeOptionId =
        listOpen && activeIndex >= 0 ? `${listId}-${activeId}` : undefined;

    useLayoutEffect(() => {
        if (previousDepartment.current === departmentId) {
            return;
        }

        previousDepartment.current = departmentId;
        setQuery(null);
        setOpen(false);
        setActiveId(null);
        if (value !== null) {
            onChange(null);
        }
    }, [departmentId, onChange, value]);

    useEffect(() => {
        if (activeOptionId) {
            document
                .getElementById(activeOptionId)
                ?.scrollIntoView?.({ block: 'nearest' });
        }
    }, [activeOptionId]);

    function close() {
        setOpen(false);
        setActiveId(null);
    }

    function select(employee: Employee) {
        onChange(employee.id);
        setQuery(null);
        close();
    }

    function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.nativeEvent.isComposing) {
            return;
        }

        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                event.preventDefault();
                setOpen(true);
                const nextIndex =
                    event.key === 'ArrowDown'
                        ? Math.min(activeIndex + 1, options.length - 1)
                        : activeIndex < 0
                          ? options.length - 1
                          : Math.max(activeIndex - 1, 0);
                setActiveId(options[nextIndex]?.id ?? null);
                break;
            }
            case 'Enter':
                if (open) {
                    event.preventDefault();
                    if (activeIndex >= 0) {
                        select(options[activeIndex]);
                    }
                }
                break;
            case 'Escape':
                if (open) {
                    event.preventDefault();
                    close();
                }
                break;
        }
    }

    return (
        <div className="min-w-0">
            <label
                htmlFor={id}
                className="mb-2 block text-[15px] font-semibold"
            >
                Nama
            </label>
            <div className="relative">
                <input
                    id={id}
                    role="combobox"
                    type="text"
                    aria-autocomplete="list"
                    aria-expanded={listOpen}
                    aria-controls={listOpen ? listId : undefined}
                    aria-activedescendant={activeOptionId}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={descriptionId}
                    autoComplete="off"
                    autoCorrect="off"
                    spellCheck={false}
                    disabled={disabled}
                    value={query ?? selected?.name ?? ''}
                    onFocus={() => setOpen(true)}
                    onClick={() => setOpen(true)}
                    onBlur={close}
                    onKeyDown={handleKeyDown}
                    onChange={(event) => {
                        setQuery(event.currentTarget.value);
                        setActiveId(null);
                        setOpen(true);
                        if (value !== null) {
                            onChange(null);
                        }
                    }}
                    className="h-[52px] w-full min-w-0 rounded-sm border border-line-control bg-surface px-3 text-[17px] text-ink disabled:bg-surface-sunken aria-invalid:border-danger aria-invalid:bg-danger-soft"
                />
                {listOpen && (
                    <ul
                        id={listId}
                        role="listbox"
                        aria-label="Hasil pencarian nama"
                        className="absolute inset-x-0 top-full z-20 mt-1 max-h-56 divide-y divide-line overflow-y-auto rounded-md border border-line bg-surface shadow-[var(--shadow-float)]"
                    >
                        {options.map((employee) => (
                            <li
                                key={employee.id}
                                id={`${listId}-${employee.id}`}
                                role="option"
                                aria-selected={
                                    activeId === null
                                        ? employee.id === value
                                        : employee.id === activeId
                                }
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => select(employee)}
                                className="flex min-h-12 cursor-pointer flex-col justify-center px-3 py-2 hover:bg-surface-sunken aria-selected:bg-primary-soft"
                            >
                                <span className="text-[17px] leading-6 font-semibold break-words">
                                    {employee.name}
                                </span>
                                <span className="tabular font-mono text-sm leading-5 text-ink-muted">
                                    NIK {employee.nik}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <div id={descriptionId} className="mt-1 text-sm">
                {error && <p className="text-danger">{error}</p>}
                {selected && query === null ? (
                    <p className="tabular font-mono text-ink-muted">
                        NIK {selected.nik}
                    </p>
                ) : (
                    !error &&
                    !unavailable && (
                        <p className="text-ink-muted">
                            {disabled
                                ? 'Pilih departemen terlebih dahulu.'
                                : 'Ketik nama, lalu pilih dari daftar.'}
                        </p>
                    )
                )}
            </div>
            <div role="status" className="text-sm text-ink-muted">
                {open && !disabled && !listOpen && (
                    <p className="mt-1">
                        Nama tidak ditemukan. Pastikan departemen sudah benar.
                    </p>
                )}
            </div>
        </div>
    );
}
