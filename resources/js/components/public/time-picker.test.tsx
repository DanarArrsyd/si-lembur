import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { breakOptions, timeOptions } from '@/lib/time-options';
import { TimePicker } from './time-picker';

const scroll = vi.fn();
const originalScroll = Object.getOwnPropertyDescriptor(
    HTMLElement.prototype,
    'scrollIntoView',
);

beforeEach(() => {
    scroll.mockClear();
    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
        configurable: true,
        value: scroll,
    });
});

afterEach(() => {
    if (originalScroll) {
        Object.defineProperty(
            HTMLElement.prototype,
            'scrollIntoView',
            originalScroll,
        );
    } else {
        Reflect.deleteProperty(HTMLElement.prototype, 'scrollIntoView');
    }
});

it('opens at the server time rounded down without selecting it', async () => {
    const onChange = vi.fn();
    render(
        <TimePicker
            label="Jam mulai"
            value={null}
            onChange={onChange}
            options={timeOptions()}
            anchor="14:07"
        />,
    );
    const trigger = screen.getByRole('button', { name: 'Jam mulai Pilih jam' });
    fireEvent.click(trigger);

    const dialog = screen.getByRole('dialog', { name: 'Jam mulai' });
    const list = within(dialog).getByRole('listbox');
    const anchor = within(list).getByRole('option', { name: '14:00' });
    expect(within(list).getAllByRole('option')).toHaveLength(96);
    expect(anchor).toHaveAttribute('aria-selected', 'false');
    expect(list).toHaveAttribute('aria-activedescendant', anchor.id);
    await waitFor(() => expect(scroll.mock.contexts.at(-1)).toBe(anchor));
    expect(list).toHaveFocus();
    expect(onChange).not.toHaveBeenCalled();
});

it('prefers the selected time, accepts a new time, and returns focus', async () => {
    function Form() {
        const [value, setValue] = useState('23:45');

        return (
            <TimePicker
                label="Jam selesai"
                value={value}
                onChange={setValue}
                options={timeOptions()}
                anchor="14:07"
            />
        );
    }

    render(<Form />);
    const trigger = screen.getByRole('button', { name: 'Jam selesai 23:45' });
    fireEvent.click(trigger);

    const selected = screen.getByRole('option', {
        name: '23:45',
        selected: true,
    });
    await waitFor(() => expect(scroll.mock.contexts.at(-1)).toBe(selected));
    fireEvent.click(screen.getByRole('option', { name: '02:00' }));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveAccessibleName('Jam selesai 02:00');
    await waitFor(() => expect(trigger).toHaveFocus());
});

it('selects numeric break values from the keyboard', () => {
    const onChange = vi.fn();
    render(
        <TimePicker
            label="Istirahat"
            value={0}
            options={breakOptions()}
            onChange={onChange}
        />,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Istirahat 0 menit' }));
    const list = screen.getByRole('listbox');
    expect(
        within(list).getByRole('option', { name: '0 menit', selected: true }),
    ).toBeInTheDocument();

    fireEvent.keyDown(list, { key: 'End' });
    fireEvent.keyDown(list, { key: 'ArrowUp' });
    fireEvent.keyDown(list, { key: 'ArrowDown' });
    fireEvent.keyDown(list, { key: 'Enter' });

    expect(onChange).toHaveBeenCalledOnce();
    expect(onChange).toHaveBeenCalledWith(240);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
});

it('dismisses without changing the time via Escape or the close button', () => {
    const onChange = vi.fn();
    render(
        <TimePicker
            label="Jam mulai"
            value={null}
            options={timeOptions()}
            onChange={onChange}
        />,
    );
    const trigger = screen.getByRole('button', { name: 'Jam mulai Pilih jam' });

    fireEvent.click(trigger);
    fireEvent.keyDown(screen.getByRole('listbox'), { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

    fireEvent.click(trigger);
    fireEvent.click(screen.getByRole('button', { name: 'Tutup' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
});
