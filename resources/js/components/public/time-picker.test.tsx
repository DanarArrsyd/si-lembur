import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { TimePicker } from '@/components/public/time-picker';
import { breakOptions, timeOptions } from '@/lib/time-options';

describe('TimePicker', () => {
    const scrolledTo: string[] = [];
    const originalScroll = Object.getOwnPropertyDescriptor(
        Element.prototype,
        'scrollIntoView',
    );

    beforeEach(() => {
        scrolledTo.length = 0;
        Object.defineProperty(Element.prototype, 'scrollIntoView', {
            configurable: true,
            value(this: Element) {
                scrolledTo.push(this.textContent ?? '');
            },
        });
    });

    afterEach(() => {
        if (originalScroll) {
            Object.defineProperty(
                Element.prototype,
                'scrollIntoView',
                originalScroll,
            );
        } else {
            Reflect.deleteProperty(Element.prototype, 'scrollIntoView');
        }
    });

    it('shows all quarter-hour options and opens at the server time rounded down', async () => {
        const onChange = vi.fn();
        render(
            <TimePicker
                label="Jam mulai"
                value={null}
                onChange={onChange}
                options={timeOptions()}
                anchor="17:08"
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: /Jam mulai.*Pilih jam/ }),
        );
        const options = within(screen.getByRole('listbox')).getAllByRole(
            'option',
        );

        expect(options).toHaveLength(96);
        expect(options[0]).toHaveTextContent('00:00');
        expect(options[95]).toHaveTextContent('23:45');
        expect(scrolledTo.at(-1)).toBe('17:00');
        expect(onChange).not.toHaveBeenCalled();
    });

    it('announces and scrolls to the selected value, then returns focus on Escape', async () => {
        const onChange = vi.fn();
        render(
            <TimePicker
                label="Jam selesai"
                value="21:15"
                onChange={onChange}
                options={timeOptions()}
                anchor="17:00"
            />,
        );
        const trigger = screen.getByRole('button', {
            name: /Jam selesai.*21:15/,
        });

        await userEvent.click(trigger);

        expect(scrolledTo.at(-1)).toBe('21:15');
        expect(screen.getByRole('option', { name: '21:15' })).toHaveAttribute(
            'aria-selected',
            'true',
        );
        await userEvent.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(trigger).toHaveFocus();
        expect(onChange).not.toHaveBeenCalled();
    });

    it('chooses the next time with ArrowDown and Enter', async () => {
        const onChange = vi.fn();
        render(
            <TimePicker
                label="Jam selesai"
                value={null}
                onChange={onChange}
                options={timeOptions()}
                anchor="23:30"
            />,
        );
        const trigger = screen.getByRole('button', { name: /Jam selesai/ });

        await userEvent.click(trigger);
        expect(screen.getByRole('listbox')).toHaveFocus();
        await userEvent.keyboard('{ArrowDown}{Enter}');

        expect(onChange).toHaveBeenCalledExactlyOnceWith('23:45');
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(trigger).toHaveFocus();
    });

    it('returns minutes when a break option is tapped and closes the sheet', async () => {
        const onChange = vi.fn();
        render(
            <TimePicker
                label="Istirahat"
                value={0}
                onChange={onChange}
                options={breakOptions()}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: /Istirahat.*0 menit/ }),
        );
        expect(screen.getAllByRole('option')).toHaveLength(17);
        await userEvent.click(screen.getByRole('option', { name: '4 jam' }));

        expect(onChange).toHaveBeenCalledExactlyOnceWith(240);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('can be dismissed with the close button or by tapping the overlay', async () => {
        const onChange = vi.fn();
        render(
            <TimePicker
                label="Jam mulai"
                value={null}
                onChange={onChange}
                options={timeOptions()}
            />,
        );
        const trigger = screen.getByRole('button', { name: /Jam mulai/ });

        await userEvent.click(trigger);
        await userEvent.click(screen.getByRole('button', { name: 'Tutup' }));
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

        await userEvent.click(trigger);
        const overlay = screen.getByTestId('bottom-sheet-overlay');
        await userEvent.click(overlay);

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(onChange).not.toHaveBeenCalled();
    });
});
