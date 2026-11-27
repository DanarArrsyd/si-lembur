import { router } from '@inertiajs/react';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import OvertimeForm from '@/pages/public/form';

const today = {
    value: 'TODAY',
    label: 'Hari ini (10 Nov 2026)',
    date: '2026-11-10',
} as const;
const yesterday = {
    value: 'YESTERDAY',
    label: 'Kemarin (09 Nov 2026)',
    date: '2026-11-09',
} as const;
const employee = { id: 7, name: 'Budi Santoso', nik: '001' };
const props = {
    departments: [{ id: 1, name: 'Produksi' }],
    dateChoices: [today, yesterday],
    serverTime: '11:59',
    maxGrossMinutes: 960,
};

beforeEach(() => {
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => new Response(JSON.stringify([employee]))),
    );
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

function pick(button: string, option: string) {
    fireEvent.click(screen.getByRole('button', { name: button }));
    fireEvent.click(screen.getByRole('option', { name: option }));
}

async function fillOvernight() {
    fireEvent.change(screen.getByLabelText('Departemen'), {
        target: { value: '1' },
    });
    const input = await screen.findByRole('combobox', { name: 'Nama' });
    fireEvent.change(input, { target: { value: 'santosoo' } });
    fireEvent.click(
        await screen.findByRole('option', { name: /Budi Santoso/ }),
    );
    fireEvent.click(screen.getByRole('radio', { name: yesterday.label }));
    pick('Jam mulai Pilih jam', '23:00');
    pick('Jam selesai Pilih jam', '02:00');
    pick('Istirahat 0 menit', '30 menit');
    fireEvent.change(screen.getByLabelText('Kegiatan lembur'), {
        target: { value: 'Menyelesaikan pesanan' },
    });
}

it('previews overnight duration, opens the server review, and retains the draft on edit', async () => {
    const post = vi
        .spyOn(router, 'post')
        .mockImplementation((_url, _data, options) => {
            options?.onFlash?.({
                review: {
                    date: '2026-11-09',
                    start_at: '2026-11-09 23:00',
                    end_at: '2026-11-10 02:00',
                    break_minutes: 30,
                    total_minutes: 150,
                    day_type: 'HARI_KERJA',
                    employee,
                    department: 'Produksi',
                    description: 'Menyelesaikan pesanan',
                    exceeds_daily_limit: true,
                    exceeds_weekly_limit: false,
                    daily_limit_minutes: 120,
                    weekly_limit_minutes: 1080,
                },
            });
        });
    render(<OvertimeForm {...props} />);
    await fillOvernight();

    expect(screen.getByText('2 jam 30 menit')).toBeVisible();
    expect(screen.getByText('2,5 jam')).toBeVisible();
    fireEvent.click(screen.getByRole('button', { name: 'Lanjut periksa' }));
    expect(post).toHaveBeenCalledWith(
        '/lembur/periksa',
        expect.objectContaining({
            employee_id: 7,
            overtime_date: '2026-11-09',
        }),
        expect.anything(),
    );
    expect(screen.getByRole('heading', { name: 'Periksa lagi' })).toBeVisible();
    expect(screen.getByText('02:00 · 10 Nov 2026')).toBeVisible();
    expect(screen.getByText(/melebihi 2 jam per hari/)).toBeVisible();
    expect(screen.getByRole('button', { name: 'Kirim lembur' })).toBeDisabled();
    fireEvent.click(screen.getByRole('button', { name: 'Ubah' }));
    expect(screen.getByRole('combobox', { name: 'Nama' })).toHaveValue(
        'Budi Santoso',
    );
    expect(screen.getByLabelText('Kegiatan lembur')).toHaveValue(
        'Menyelesaikan pesanan',
    );
    expect(fetch).toHaveBeenCalledOnce();
});

it('shows equal times under the end time and summarizes missing fields without a request', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<OvertimeForm {...props} />);
    pick('Jam mulai Pilih jam', '17:00');
    pick('Jam selesai Pilih jam', '17:00');
    expect(
        screen.getByRole('button', { name: 'Jam selesai 17:00' }),
    ).toHaveAccessibleDescription(
        'Jam selesai tidak boleh sama dengan jam mulai.',
    );

    fireEvent.click(screen.getByRole('button', { name: 'Lanjut periksa' }));
    expect(
        within(
            screen.getByRole('region', { name: 'Periksa isian' }),
        ).getAllByRole('link').length,
    ).toBeGreaterThan(1);
    expect(post).not.toHaveBeenCalled();
});

it('keeps the draft and shows a server cutoff error under the date choices', async () => {
    const message =
        'Batas input lembur kemarin sudah lewat (12:00). Hubungi supervisor untuk input lembur kemarin.';
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onError?.({ overtime_date: message });
    });
    const { rerender } = render(<OvertimeForm {...props} />);
    await fillOvernight();

    fireEvent.click(screen.getByRole('button', { name: 'Lanjut periksa' }));
    rerender(<OvertimeForm {...props} dateChoices={[today]} />);
    expect(
        screen.getByRole('group', { name: 'Tanggal lembur' }),
    ).toHaveAccessibleDescription(message);
    expect(
        screen.queryByRole('radio', { name: yesterday.label }),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('radio', { name: today.label })).not.toBeChecked();
    expect(screen.getByRole('combobox', { name: 'Nama' })).toHaveValue(
        'Budi Santoso',
    );
    expect(
        screen.getByRole('button', { name: 'Jam mulai 23:00' }),
    ).toBeVisible();
});
