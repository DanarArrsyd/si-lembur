import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import OvertimeFormPage from '@/pages/panel/overtimes/form';

const formProps = {
    dateBounds: { min: '2026-10-01', max: '2026-11-28' },
    serverTime: '18:45',
    maxGrossMinutes: 960,
};

describe('panel overtime form', () => {
    beforeEach(() => {
        window.HTMLElement.prototype.scrollIntoView = vi.fn();
    });

    it('previews an overnight shift after break', async () => {
        const user = userEvent.setup();
        render(
            <OvertimeFormPage
                {...formProps}
                fixedDepartmentId={1}
                departments={[
                    {
                        id: 1,
                        name: 'Produksi',
                        employees: [
                            { id: 1, name: 'Budi Santoso', nik: 'KMI01' },
                        ],
                    },
                ]}
            />,
        );
        await user.click(screen.getByRole('button', { name: /Jam mulai/ }));
        await user.click(screen.getByRole('option', { name: '23:00' }));
        await user.click(screen.getByRole('button', { name: /Jam selesai/ }));
        await user.click(screen.getByRole('option', { name: '02:00' }));
        await user.click(screen.getByRole('button', { name: /Istirahat/ }));
        await user.click(screen.getByRole('option', { name: '30 menit' }));

        expect(screen.getByRole('status')).toHaveTextContent('2 jam 30 menit');
        expect(screen.getByRole('status')).toHaveTextContent(
            'Jam selesai jatuh pada hari berikutnya.',
        );
    });

    it('renders the historical identity as read-only while editing', () => {
        render(
            <OvertimeFormPage
                {...formProps}
                overtime={{
                    id: 2,
                    number: 'LBR-20261121-0001',
                    department: 'Produksi',
                    employee: { name: 'Budi Santoso', nik: 'KMI01' },
                    overtime_date: '2026-11-21',
                    start_time: '23:00',
                    end_time: '02:00',
                    break_minutes: 0,
                    description: 'Menyelesaikan pesanan produksi',
                }}
            />,
        );
        expect(screen.getByLabelText('Departemen')).toHaveAttribute('readonly');
        expect(screen.getByLabelText('Departemen')).toHaveValue('Produksi');
        expect(screen.getByLabelText('Nama')).toHaveAttribute('readonly');
        expect(screen.getByLabelText('Nama')).toHaveValue(
            'Budi Santoso · NIK KMI01',
        );
        expect(screen.queryByRole('combobox')).toBeNull();
        expect(screen.getByRole('status')).toHaveTextContent('3 jam');
    });
});
