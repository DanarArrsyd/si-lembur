import { fireEvent, render, screen, within } from '@testing-library/react';
import { useState } from 'react';
import { expect, it, vi } from 'vitest';
import { Typeahead } from './typeahead';

const employees = [
    { id: 1, nik: '001', name: 'Agus Setiawan' },
    { id: 2, nik: '002', name: 'Agus Setiawan' },
];

function Form({ departmentId = 1 }: { departmentId?: number | null }) {
    const [value, setValue] = useState<number | null>(null);

    return (
        <>
            <Typeahead
                departmentId={departmentId}
                employees={employees}
                value={value}
                onChange={setValue}
            />
            <input type="hidden" name="employee_id" value={value ?? ''} />
        </>
    );
}

it('disambiguates duplicate names and accepts the second result with the keyboard', () => {
    const { container } = render(<Form />);
    const input = screen.getByRole('combobox', { name: 'Nama' });

    fireEvent.change(input, { target: { value: 'agus' } });
    const list = screen.getByRole('listbox');
    const [first, second] = within(list).getAllByRole('option');
    expect(first).toHaveTextContent('NIK 001');
    expect(second).toHaveTextContent('NIK 002');
    expect(input).toHaveAttribute('aria-controls', list.id);

    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    expect(input).toHaveAttribute('aria-activedescendant', second.id);
    fireEvent.keyDown(input, { key: 'ArrowUp' });
    expect(input).toHaveAttribute('aria-activedescendant', first.id);
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(input).toHaveValue('Agus Setiawan');
    expect(input).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByText('NIK 002')).toBeVisible();
    expect(container.querySelector('[name="employee_id"]')).toHaveValue('2');
});

it('clears the selected employee when the department changes', () => {
    const { container, rerender } = render(<Form />);
    const input = screen.getByRole('combobox', { name: 'Nama' });

    fireEvent.focus(input);
    fireEvent.click(screen.getByRole('option', { name: /001/ }));
    expect(container.querySelector('[name="employee_id"]')).toHaveValue('1');

    rerender(<Form departmentId={2} />);
    expect(input).toHaveValue('');
    expect(input).toHaveAttribute('aria-expanded', 'false');
    expect(container.querySelector('[name="employee_id"]')).toHaveValue('');
});

it('shows an empty state, closes on Escape, and does not commit typed text', () => {
    const onChange = vi.fn();
    render(
        <Typeahead
            departmentId={1}
            employees={employees}
            value={null}
            onChange={onChange}
        />,
    );
    const input = screen.getByRole('combobox', { name: 'Nama' });

    fireEvent.change(input, { target: { value: 'xyz' } });
    expect(screen.getByRole('status')).toHaveTextContent(
        'Nama tidak ditemukan. Pastikan departemen sudah benar.',
    );

    fireEvent.keyDown(input, { key: 'Enter' });
    expect(onChange).not.toHaveBeenCalled();
    fireEvent.keyDown(input, { key: 'Escape' });
    expect(input).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('status')).toBeEmptyDOMElement();
});

it('clears a selected ID when the employee name is edited', () => {
    const { container } = render(<Form />);
    const input = screen.getByRole('combobox', { name: 'Nama' });

    fireEvent.focus(input);
    fireEvent.click(screen.getByRole('option', { name: /001/ }));
    fireEvent.change(input, { target: { value: 'Agus' } });
    expect(container.querySelector('[name="employee_id"]')).toHaveValue('');
});

it('disables the field until there is a department and describes validation errors', () => {
    const { rerender } = render(<Form departmentId={null} />);
    expect(screen.getByRole('combobox', { name: 'Nama' })).toBeDisabled();

    rerender(
        <Typeahead
            departmentId={1}
            employees={employees}
            value={null}
            onChange={vi.fn()}
            error="Pilih nama dari daftar."
        />,
    );
    const input = screen.getByRole('combobox', { name: 'Nama' });
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAccessibleDescription('Pilih nama dari daftar.');
});
