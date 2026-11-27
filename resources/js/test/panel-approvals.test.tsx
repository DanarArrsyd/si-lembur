import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
import Approvals from '@/pages/panel/approvals';
import type { ApprovalQueue, PanelOvertimeRow } from '@/types/panel-overtime';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock('@inertiajs/react', async () => {
    const mock = (await import('@/test/inertia-mock')).inertiaMock();
    return { ...mock, router: { ...mock.router, post } };
});

function row(id: number): PanelOvertimeRow {
    return {
        id,
        number: `LBR-20261128-000${id}`,
        employee: { id, name: `Karyawan ${id}`, nik: `KMI000${id}` },
        department: { id: 1, name: 'Produksi' },
        date: '2026-11-28',
        start_at: '2026-11-28 05:00',
        end_at: '2026-11-28 06:00',
        break_minutes: 0,
        total_minutes: 60,
        status: 'PENDING',
        description: 'Mengecek pesanan produksi',
        exceeds_daily_limit: false,
        exceeds_weekly_limit: false,
    };
}

const queue: ApprovalQueue = {
    data: [row(1), row(2)],
    current_page: 1,
    last_page: 2,
    next_page_url: '/panel/approvals?page=2',
    prev_page_url: null,
    total: 3,
};

beforeEach(() => post.mockReset());

it('selects the current page and posts only its IDs', async () => {
    const user = userEvent.setup();
    render(<Approvals queue={queue} />);

    await user.click(
        screen.getByRole('checkbox', { name: 'Pilih semua di halaman ini' }),
    );
    expect(screen.getByText('2 dipilih')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Setujui terpilih' }));

    expect(post).toHaveBeenCalledExactlyOnceWith(
        '/panel/approvals/bulk-approve',
        { ids: [1, 2] },
        expect.anything(),
    );
});

it('clears selection when navigating to another page', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<Approvals queue={queue} />);
    await user.click(
        screen.getByRole('checkbox', { name: 'Pilih semua di halaman ini' }),
    );

    rerender(
        <Approvals queue={{ ...queue, current_page: 2, data: [row(3)] }} />,
    );

    expect(
        screen.getByRole('checkbox', { name: 'Pilih semua di halaman ini' }),
    ).not.toBeChecked();
    expect(
        screen.getByRole('button', { name: 'Setujui terpilih' }),
    ).toBeDisabled();
});

it('asks for a public-safe reason and submits a trimmed rejection', async () => {
    const user = userEvent.setup();
    render(<Approvals queue={queue} />);
    const cards = screen.getByRole('list', { name: 'Antrean persetujuan' });
    await user.click(
        within(cards).getByRole('button', { name: 'Tolak LBR-20261128-0001' }),
    );

    const dialog = screen.getByRole('dialog', { name: 'Tolak pengajuan' });
    expect(
        within(dialog).getByText(/Alasan tampil di Cek status/),
    ).toBeInTheDocument();
    const submit = within(dialog).getByRole('button', {
        name: 'Tolak pengajuan',
    });
    await user.click(submit);
    expect(post).not.toHaveBeenCalled();
    expect(within(dialog).getByRole('alert')).toHaveTextContent(
        'Tulis alasan minimal 5 huruf.',
    );

    await user.type(
        within(dialog).getByLabelText('Alasan penolakan'),
        '  Periksa jam selesai.  ',
    );
    await user.click(submit);

    expect(post).toHaveBeenCalledExactlyOnceWith(
        '/panel/approvals/1/reject',
        { reason: 'Periksa jam selesai.' },
        expect.anything(),
    );
});
