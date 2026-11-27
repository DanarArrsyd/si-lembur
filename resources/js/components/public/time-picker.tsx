import { useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { roundDownTime } from '@/lib/time-options';
import { BottomSheet } from './bottom-sheet';

type PickerValue = string | number;

type Props<Value extends PickerValue> = {
    label: string;
    value: Value | null;
    onChange: (value: Value) => void;
    options: readonly (Value | { value: Value; label: string })[];
    anchor?: Value;
};

export function TimePicker<Value extends PickerValue>({
    label,
    value,
    onChange,
    options,
    anchor,
}: Props<Value>) {
    const id = useId();
    const labelId = `${id}-label`;
    const valueId = `${id}-value`;
    const list = useRef<HTMLUListElement>(null);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const items = options.map((option) =>
        typeof option === 'object'
            ? option
            : { value: option, label: String(option) },
    );
    const selected = items.findIndex((option) => option.value === value);
    const anchorValue =
        typeof anchor === 'string' ? (roundDownTime(anchor) ?? anchor) : anchor;
    const initialIndex =
        selected >= 0
            ? selected
            : Math.max(
                  0,
                  items.findIndex((option) => option.value === anchorValue),
              );
    const optionId = (index: number) => `${id}-option-${index}`;

    function select(index: number) {
        const option = items[index];
        if (option) {
            onChange(option.value);
            setOpen(false);
        }
    }

    function moveTo(index: number) {
        setActiveIndex(index);
        list.current?.children
            .item(index)
            ?.scrollIntoView?.({ block: 'nearest' });
    }

    function handleKeyDown(event: KeyboardEvent<HTMLUListElement>) {
        const lastIndex = items.length - 1;

        switch (event.key) {
            case 'ArrowDown':
                moveTo(Math.min(activeIndex + 1, lastIndex));
                break;
            case 'ArrowUp':
                moveTo(Math.max(activeIndex - 1, 0));
                break;
            case 'Home':
                moveTo(0);
                break;
            case 'End':
                moveTo(lastIndex);
                break;
            case 'Enter':
            case ' ':
                select(activeIndex);
                break;
            default:
                return;
        }

        event.preventDefault();
    }

    return (
        <div className="min-w-0">
            <span id={labelId} className="mb-2 block text-[15px] font-semibold">
                {label}
            </span>
            <BottomSheet
                open={open}
                onOpenChange={(nextOpen) => {
                    if (nextOpen) {
                        setActiveIndex(initialIndex);
                    }
                    setOpen(nextOpen);
                }}
                title={label}
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    list.current?.focus({ preventScroll: true });
                    list.current?.children
                        .item(initialIndex)
                        ?.scrollIntoView?.({ block: 'center' });
                }}
                trigger={
                    <button
                        type="button"
                        aria-labelledby={`${labelId} ${valueId}`}
                        disabled={items.length === 0}
                        className="flex h-[52px] w-full items-center justify-between gap-3 rounded-sm border border-line-control bg-surface px-3 text-left text-[17px] text-ink disabled:bg-surface-sunken"
                    >
                        {selected >= 0 ? (
                            <span id={valueId} className="tabular font-mono">
                                {items[selected].label}
                            </span>
                        ) : (
                            <span id={valueId} className="text-ink-muted">
                                Pilih jam
                            </span>
                        )}
                        <span
                            aria-hidden="true"
                            className="mr-1 size-2 shrink-0 -translate-y-0.5 rotate-45 border-r border-b border-ink-muted"
                        />
                    </button>
                }
            >
                <ul
                    ref={list}
                    role="listbox"
                    tabIndex={0}
                    aria-label={label}
                    aria-activedescendant={optionId(activeIndex)}
                    onKeyDown={handleKeyDown}
                    className="min-h-0 divide-y divide-line overflow-y-auto overscroll-contain focus-visible:outline-offset-[-2px]"
                >
                    {items.map((option, index) => (
                        <li
                            key={option.value}
                            id={optionId(index)}
                            role="option"
                            aria-selected={index === selected}
                            data-active={index === activeIndex}
                            onClick={() => select(index)}
                            className="tabular flex h-[52px] cursor-pointer items-center justify-between gap-3 px-4 font-mono text-[17px] text-ink hover:bg-surface-sunken aria-selected:bg-primary-soft data-[active=true]:outline-2 data-[active=true]:outline-offset-[-2px] data-[active=true]:outline-primary"
                        >
                            <span>{option.label}</span>
                            {index === selected && (
                                <span
                                    aria-hidden="true"
                                    className="font-sans text-sm font-semibold text-ink-muted"
                                >
                                    Terpilih
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            </BottomSheet>
        </div>
    );
}
