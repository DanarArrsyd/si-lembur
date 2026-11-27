import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { Typeahead } from '@/components/public/typeahead';
import type { EmployeeOption } from '@/types/public';

const employees: EmployeeOption[] = [
    { id: 1, nik: 'KMI0001', name: 'Budi Santoso' },
    { id: 3, nik: 'KMI0003', name: 'Agus Setiawan' },
    { id: 4, nik: 'KMI0004', name: 'Agus Setiawan' },
];

/** Pembungkus yang menyimpan nilai seperti form sungguhan. */
function Harness({
    departmentId = 1,
    onChange = vi.fn(),
    error,
    loading,
}: {
    departmentId?: number | null;
    onChange?: (value: EmployeeOption | null) => void;
    error?: string;
    loading?: boolean;
}) {
    const [value, setValue] = useState<EmployeeOption | null>(null);

    return (
        <Typeahead
            departmentId={departmentId}
            employees={employees}
            loading={loading}
            value={value}
            error={error}
            onChange={(next) => {
                setValue(next);
                onChange(next);
            }}
        />
    );
}

const input = () => screen.getByRole('combobox', { name: 'Nama' });

describe('Typeahead', () => {
    it('finds a name despite a typo and shows the NIK', async () => {
        render(<Harness />);
        await userEvent.type(input(), 'santosoo');

        const option = screen.getByRole('option', { name: /Budi Santoso/ });
        expect(option).toHaveTextContent('KMI0001');
        expect(input()).toHaveAttribute('aria-expanded', 'true');
    });

    it('sends the id of the clicked employee when two share a name', async () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);
        await userEvent.type(input(), 'agus');

        const options = screen.getAllByRole('option');
        expect(options).toHaveLength(2);
        expect(options[1]).toHaveTextContent('KMI0004');

        await userEvent.click(options[1]);

        expect(onChange).toHaveBeenLastCalledWith(employees[2]);
        expect(input()).toHaveValue('Agus Setiawan');
        expect(input()).toHaveAttribute('aria-expanded', 'false');
        expect(screen.getByText('KMI0004')).toBeInTheDocument();
    });

    it('selects with the keyboard: down, down, Enter picks the second option', async () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);
        await userEvent.type(input(), 'agus');

        await userEvent.keyboard('{ArrowDown}');
        const first = screen.getAllByRole('option')[0];
        expect(input()).toHaveAttribute('aria-activedescendant', first.id);
        expect(first).toHaveAttribute('aria-selected', 'true');

        await userEvent.keyboard('{ArrowDown}{Enter}');

        expect(onChange).toHaveBeenLastCalledWith(employees[2]);
    });

    it('closes the list with Escape without choosing', async () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);
        await userEvent.type(input(), 'budi');
        await userEvent.keyboard('{Escape}');

        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
        expect(onChange).not.toHaveBeenCalledWith(employees[0]);
    });

    it('treats typed text as no selection until a name is picked', async () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);
        await userEvent.type(input(), 'budi');
        await userEvent.click(screen.getByRole('option', { name: /Budi/ }));
        await userEvent.type(input(), 'x');

        expect(onChange).toHaveBeenLastCalledWith(null);
    });

    it('shows the empty state when no name matches', async () => {
        render(<Harness />);
        await userEvent.type(input(), 'wahyudi');

        expect(
            screen.getByText(
                'Nama tidak ditemukan. Pastikan departemen sudah benar.',
            ),
        ).toBeInTheDocument();
        expect(screen.queryByRole('option')).not.toBeInTheDocument();
    });

    it('clears the chosen name when the department changes', async () => {
        const onChange = vi.fn();
        const { rerender } = render(<Harness onChange={onChange} />);
        await userEvent.type(input(), 'budi');
        await userEvent.click(screen.getByRole('option', { name: /Budi/ }));

        rerender(<Harness departmentId={2} onChange={onChange} />);

        expect(onChange).toHaveBeenLastCalledWith(null);
        expect(input()).toHaveValue('');
    });

    it('is disabled until a department is chosen, and while loading', () => {
        const { rerender } = render(<Harness departmentId={null} />);
        expect(input()).toBeDisabled();
        expect(screen.getByText('Pilih departemen dulu.')).toBeInTheDocument();

        rerender(<Harness loading />);
        expect(input()).toBeDisabled();
        expect(screen.getByText('Memuat daftar nama…')).toBeInTheDocument();
    });

    it('links the error message to the input', () => {
        render(<Harness error="Pilih nama dari daftar." />);

        expect(input()).toHaveAttribute('aria-invalid', 'true');
        expect(input()).toHaveAccessibleDescription(/Pilih nama dari daftar\./);
    });
});
