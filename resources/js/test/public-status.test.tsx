import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { StatusPill } from '@/components/public/status-pill';
import StatusCheck from '@/pages/public/status';
import type { PublicStatusProps } from '@/types/public';

vi.mock('@inertiajs/react', async () =>
    (await import('@/test/inertia-mock')).inertiaMock(),
);

const employee = { id: 2, nik: 'KMI0002', name: 'Budi Santoso' };
const props: PublicStatusProps = {
    departments: [
        { id: 1, name: 'Produksi' },
        { id: 3, name: 'Gudang' },
    ],
    prefillDepartmentId: 1,
    prefillEmployee: employee,
};

const history = [
    {
        number: 'LBR-20261127-0001',
        date: '2026-11-27',
        start: '2026-11-27 00:15',
        end: '2026-11-27 03:15',
        total_minutes: 180,
        status: 'REJECTED',
        status_label: 'Ditolak',
        reject_reason: 'Periksa jam selesai.',
        can_resubmit: true,
    },
    {
        number: 'LBR-20261124-0001',
        date: '2026-11-24',
        start: '2026-11-24 23:00',
        end: '2026-11-25 02:00',
        total_minutes: 165,
        status: 'REJECTED',
        status_label: 'Ditolak',
        reject_reason: 'Periksa jam mulai.',
        can_resubmit: false,
    },
];

const fetchResponse = vi.fn();

beforeEach(() => {
    fetchResponse.mockReset();
    fetchResponse.mockImplementation((url: string) => {
        const data = url === '/api/status?employee_id=2' ? history : [employee];
        return Promise.resolve(Response.json(data));
    });
    vi.stubGlobal('fetch', fetchResponse);
});

afterEach(() => vi.unstubAllGlobals());

it('selects the prefilled employee and loads the status list automatically', async () => {
    render(<StatusCheck {...props} />);

    expect(screen.getByLabelText('Departemen')).toHaveValue('1');
    expect(screen.getByRole('combobox', { name: 'Nama' })).toHaveValue(
        'Budi Santoso',
    );
    expect(await screen.findByText('LBR-20261127-0001')).toBeInTheDocument();
    expect(screen.getByText('Periksa jam selesai.')).toBeInTheDocument();
    expect(screen.getByText(/02:00.*25 Nov 2026/)).toBeInTheDocument();
    expect(
        screen.getByRole('link', { name: 'Ajukan ulang lewat form lembur.' }),
    ).toHaveAttribute('href', '/');
    expect(
        screen.getByText(
            'Hubungi supervisor untuk mengajukan ulang lembur ini.',
        ),
    ).toBeInTheDocument();
});

it('clears old history when the department changes', async () => {
    const user = userEvent.setup();
    render(<StatusCheck {...props} />);
    await screen.findByText('LBR-20261127-0001');

    await user.selectOptions(screen.getByLabelText('Departemen'), '3');

    expect(screen.queryByText('LBR-20261127-0001')).not.toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Nama' })).toHaveValue('');
    await waitFor(() =>
        expect(fetchResponse).toHaveBeenCalledWith(
            '/api/departments/3/employees',
            expect.anything(),
        ),
    );
});

it('loads an empty result when an employee is selected manually', async () => {
    const user = userEvent.setup();
    fetchResponse.mockImplementation((url: string) =>
        Promise.resolve(
            Response.json(url.startsWith('/api/status') ? [] : [employee]),
        ),
    );
    render(
        <StatusCheck
            {...props}
            prefillDepartmentId={null}
            prefillEmployee={null}
        />,
    );

    await user.selectOptions(screen.getByLabelText('Departemen'), '1');
    const input = screen.getByRole('combobox', { name: 'Nama' });
    await waitFor(() => expect(input).toBeEnabled());
    await user.type(input, 'santosoo');
    await user.click(screen.getByRole('option', { name: /Budi Santoso/ }));

    expect(
        await screen.findByText('Belum ada pengajuan dalam 60 hari terakhir.'),
    ).toBeInTheDocument();
});

it.each([
    ['PENDING', 'Menunggu'],
    ['APPROVED', 'Disetujui'],
    ['REJECTED', 'Ditolak'],
] as const)('renders the %s status with a text label', (status, label) => {
    render(<StatusPill status={status} />);
    expect(screen.getByText(label)).toBeVisible();
});
