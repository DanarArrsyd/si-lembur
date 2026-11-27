import clsx from 'clsx';
import { CheckIcon, ChevronDownIcon } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { BottomSheet } from '@/components/public/bottom-sheet';
import { roundTimeDown } from '@/lib/time-options';

type Option<Value> = Value | { value: Value; label: string };

type Props<Value extends string | number> = {
    id?: string;
    label: string;
    error?: string;
    value: Value | null;
    onChange: (value: Value) => void;
    options: readonly Option<Value>[];
    anchor?: Value;
};

export function TimePicker<Value extends string | number>({
    id: fieldId,
    label,
    error,
    value,
    onChange,
    options,
    anchor,
}: Props<Value>) {
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const list = useRef<HTMLDivElement>(null);
    const id = useId();
    const choices = options.map((option) =>
        typeof option === 'object'
            ? option
            : { value: option, label: String(option) },
    );
    const selectedIndex = choices.findIndex((choice) => choice.value === value);
    const selected = choices[selectedIndex];
    const optionId = (index: number) => `${id}-option-${index}`;

    function changeOpen(next: boolean) {
        if (next) {
            const alignedAnchor =
                typeof anchor === 'string' ? roundTimeDown(anchor) : anchor;
            const anchorIndex = choices.findIndex(
                (choice) => choice.value === alignedAnchor,
            );
            setActiveIndex(
                selectedIndex >= 0 ? selectedIndex : Math.max(anchorIndex, 0),
            );
        }

        setOpen(next);
    }

    function scrollTo(index: number, block: ScrollLogicalPosition) {
        list.current?.children.item(index)?.scrollIntoView({ block });
    }

    function choose(option: { value: Value }) {
        onChange(option.value);
        setOpen(false);
    }

    function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        let next: number;

        switch (event.key) {
            case 'ArrowDown':
                next = Math.min(activeIndex + 1, choices.length - 1);
                break;
            case 'ArrowUp':
                next = Math.max(activeIndex - 1, 0);
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = choices.length - 1;
                break;
            case 'Enter':
            case ' ':
                event.preventDefault();
                if (choices[activeIndex]) {
                    choose(choices[activeIndex]);
                }
                return;
            default:
                return;
        }

        event.preventDefault();
        setActiveIndex(next);
        scrollTo(next, 'nearest');
    }

    return (
        <div className="grid gap-1.5">
            <span
                id={`${id}-label`}
                className="text-[15px] font-semibold text-ink"
            >
                {label}
            </span>
            <BottomSheet
                open={open}
                onOpenChange={changeOpen}
                title={label}
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    list.current?.focus({ preventScroll: true });
                    scrollTo(activeIndex, 'center');
                }}
                trigger={
                    <button
                        id={fieldId}
                        type="button"
                        aria-invalid={!!error}
                        aria-describedby={error ? `${id}-error` : undefined}
                        disabled={choices.length === 0}
                        aria-labelledby={`${id}-label ${id}-value`}
                        className={clsx(
                            'flex h-[52px] w-full items-center justify-between gap-2 rounded-sm border px-3 text-left text-[17px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50',
                            error
                                ? 'border-danger bg-danger-soft'
                                : 'border-line-control bg-surface',
                        )}
                    >
                        <span
                            id={`${id}-value`}
                            className={
                                selected
                                    ? 'font-mono text-ink tabular-nums'
                                    : 'text-ink-muted'
                            }
                        >
                            {selected?.label ?? 'Pilih jam'}
                        </span>
                        <ChevronDownIcon
                            aria-hidden="true"
                            className="size-4 shrink-0 text-ink-muted"
                            strokeWidth={1.5}
                        />
                    </button>
                }
            >
                <div
                    ref={list}
                    role="listbox"
                    tabIndex={0}
                    aria-labelledby={`${id}-label`}
                    aria-activedescendant={optionId(activeIndex)}
                    className="min-h-0 overflow-y-auto overscroll-contain px-4 focus:outline-none"
                    onKeyDown={handleKeyDown}
                >
                    {choices.map((choice, index) => (
                        <button
                            key={choice.value}
                            id={optionId(index)}
                            type="button"
                            role="option"
                            aria-selected={index === selectedIndex}
                            tabIndex={-1}
                            onClick={() => choose(choice)}
                            className={clsx(
                                'flex h-[52px] w-full items-center justify-between border-b border-line px-3 text-left font-mono text-[17px] text-ink tabular-nums last:border-b-0 hover:bg-surface-sunken',
                                index === selectedIndex &&
                                    'bg-primary-soft font-semibold text-primary-hover',
                                index === activeIndex &&
                                    'outline-2 -outline-offset-2 outline-primary',
                            )}
                        >
                            {choice.label}
                            {index === selectedIndex && (
                                <CheckIcon
                                    aria-hidden="true"
                                    className="size-4 shrink-0"
                                    strokeWidth={1.5}
                                />
                            )}
                        </button>
                    ))}
                </div>
            </BottomSheet>
            {error && (
                <p id={`${id}-error`} className="text-sm text-danger">
                    {error}
                </p>
            )}
        </div>
    );
}
