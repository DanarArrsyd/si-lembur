import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';
import StatusPage from '@/pages/public/status';

it('explains where employees find their private link without showing records', () => {
    render(<StatusPage history={null} />);

    expect(
        screen.getByRole('heading', { name: 'Cek status pengajuan' }),
    ).toBeVisible();
    expect(screen.getByText(/bukti pengajuan/i)).toBeVisible();
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
});

it('shows textual status, overnight date, and the rejection reason', () => {
    render(
        <StatusPage
            history={{
                employeeName: 'Budi Santoso',
                items: [
                    {
                        number: 'LBR-20261127-0001',
                        date: '2026-11-26',
                        start_at: '2026-11-26 23:00',
                        end_at: '2026-11-27 02:00',
                        total_minutes: 150,
                        status: 'REJECTED',
                        reject_reason: 'Jam belum sesuai dengan catatan.',
                    },
                ],
            }}
        />,
    );

    expect(screen.getByText('Budi Santoso')).toBeVisible();
    expect(screen.getByText('Ditolak')).toBeVisible();
    expect(screen.getByText('Jam belum sesuai dengan catatan.')).toBeVisible();
    expect(screen.getByText(/02:00 · 27 Nov 2026/)).toBeVisible();
    expect(screen.getByText('2 jam 30 menit')).toBeVisible();
});

it('shows an empty state or guides users with an invalid link', () => {
    const { rerender } = render(
        <StatusPage history={{ employeeName: 'Budi', items: [] }} />,
    );
    expect(
        screen.getByText('Belum ada pengajuan dalam 60 hari terakhir.'),
    ).toBeVisible();

    rerender(<StatusPage history={null} invalidLink />);
    expect(
        screen.getByRole('heading', { name: 'Tautan tidak berlaku' }),
    ).toBeVisible();
    expect(screen.getByText(/hubungi supervisor/i)).toBeVisible();
});
