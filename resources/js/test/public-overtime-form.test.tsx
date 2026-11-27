import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import OvertimeForm from '@/pages/public/form';
import type { PublicOvertimeFormProps } from '@/types/public';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock('@inertiajs/react', () => ({ router: { post } }));

const props: PublicOvertimeFormProps = {
    departments: [{ id: 1, name: 'Produksi' }],
    dateChoices: [
        { value: 'TODAY', label: 'Hari ini (10 Nov 2026)', date: '2026-11-10' },
        {
            value: 'YESTERDAY',
            label: 'Kemarin (09 Nov 2026)',
            date: '2026-11-09',
        },
    ],
    serverTime: '11:59',
    maxGrossMinutes: 960,
};

beforeEach(() => {
    post.mockReset();
    vi.stubGlobal(
        'fetch',
        vi
            .fn()
            .mockResolvedValue(
                Response.json([
                    { id: 2, name: 'Budi Santoso', nik: 'KMI0002' },
                ]),
            ),
    );
    Object.defineProperty(Element.prototype, 'scrollIntoView', {
        configurable: true,
        value: vi.fn(),
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
    Reflect.deleteProperty(Element.prototype, 'scrollIntoView');
});

async function selectOption(
    user: ReturnType<typeof userEvent.setup>,
    field: string,
    value: string,
) {
    await user.click(screen.getByRole('button', { name: new RegExp(field) }));
    await user.click(screen.getByRole('option', { name: value }));
}

it('shows the overnight net duration while the user fills the form', async () => {
    const user = userEvent.setup();
    render(<OvertimeForm {...props} />);

    await selectOption(user, 'Jam mulai', '23:00');
    await selectOption(user, 'Jam selesai', '02:00');
    await selectOption(user, 'Istirahat', '30 menit');

    expect(screen.getByText(/2 jam 30 menit/)).toBeInTheDocument();
    expect(screen.getByText(/2,5 jam/)).toBeInTheDocument();
    expect(post).not.toHaveBeenCalled();
});

it('shows the matching time error without posting', async () => {
    const user = userEvent.setup();
    render(<OvertimeForm {...props} />);

    await selectOption(user, 'Jam mulai', '17:00');
    await selectOption(user, 'Jam selesai', '17:00');
    await user.click(screen.getByRole('button', { name: 'Lanjut periksa' }));

    expect(
        screen.getByRole('button', { name: /Jam selesai/ }),
    ).toHaveAccessibleDescription(
        'Jam selesai tidak boleh sama dengan jam mulai.',
    );
    expect(post).not.toHaveBeenCalled();
});

it('lists multiple errors in a summary linked to their fields', async () => {
    const user = userEvent.setup();
    render(<OvertimeForm {...props} />);

    await user.click(screen.getByRole('button', { name: 'Lanjut periksa' }));

    const summary = screen.getByRole('alert');
    const link = within(summary).getByRole('link', {
        name: 'Pilih departemen.',
    });
    expect(link).toHaveAttribute(
        'href',
        '#' + screen.getByLabelText('Departemen').id,
    );
    expect(
        within(summary).getByRole('link', { name: 'Pilih jam mulai.' }),
    ).toBeInTheDocument();
});

async function completeForm(user: ReturnType<typeof userEvent.setup>) {
    await user.selectOptions(screen.getByLabelText('Departemen'), '1');
    const employee = screen.getByRole('combobox', { name: 'Nama' });
    await waitFor(() => expect(employee).toBeEnabled());
    await user.type(employee, 'budi');
    await user.click(screen.getByRole('option', { name: /Budi Santoso/ }));
    await user.click(screen.getByRole('radio', { name: /Kemarin/ }));
    await selectOption(user, 'Jam mulai', '23:00');
    await selectOption(user, 'Jam selesai', '02:00');
    await user.type(
        screen.getByLabelText('Kegiatan lembur'),
        'Menyelesaikan pesanan',
    );
}

it('places the server cutoff error under Tanggal and keeps other input', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<OvertimeForm {...props} />);
    await completeForm(user);

    const message = 'Batas input lembur kemarin sudah lewat (12:00).';
    post.mockImplementation(
        (
            _url,
            _data,
            options: {
                onError: (errors: Record<string, string>) => void;
                onFinish: () => void;
            },
        ) => {
            options.onError({ overtime_date: message });
            options.onFinish();
        },
    );
    await user.click(screen.getByRole('button', { name: 'Lanjut periksa' }));
    rerender(<OvertimeForm {...props} dateChoices={[props.dateChoices[0]]} />);

    expect(post).toHaveBeenCalledWith(
        '/lembur/periksa',
        expect.objectContaining({
            date_choice: 'YESTERDAY',
            overtime_date: '2026-11-09',
            employee_id: 2,
        }),
        expect.anything(),
    );
    expect(
        screen.getByRole('group', { name: 'Tanggal lembur' }),
    ).toHaveAccessibleDescription(message);
    expect(
        screen.queryByRole('radio', { name: /Kemarin/ }),
    ).not.toBeInTheDocument();
    expect(screen.getByLabelText('Kegiatan lembur')).toHaveValue(
        'Menyelesaikan pesanan',
    );
});

async function openReview(user: ReturnType<typeof userEvent.setup>) {
    post.mockImplementationOnce(
        (
            _url,
            _data,
            options: { onFlash: (data: unknown) => void; onFinish: () => void },
        ) => {
            options.onFlash({
                review: {
                    date: '2026-11-09',
                    start_at: '2026-11-09 23:00',
                    end_at: '2026-11-10 02:00',
                    break_minutes: 0,
                    total_minutes: 180,
                    day_type: 'HARI_KERJA',
                    exceeds_daily_limit: true,
                    exceeds_weekly_limit: false,
                    daily_limit_minutes: 150,
                    weekly_limit_minutes: 1080,
                    employee: { name: 'Budi Santoso', nik: 'KMI0002' },
                    department: 'Produksi',
                    description: 'Menyelesaikan pesanan',
                },
            });
            options.onFinish();
        },
    );

    await user.click(screen.getByRole('button', { name: 'Lanjut periksa' }));
}

it('shows the server review and restores the draft when Ubah is selected', async () => {
    const user = userEvent.setup();
    render(<OvertimeForm {...props} />);
    await completeForm(user);

    await openReview(user);
    expect(
        screen.getByRole('heading', { name: 'Periksa lagi' }),
    ).toBeInTheDocument();
    expect(
        screen.getByText(/Total lembur melebihi 2,5 jam per hari/),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Ubah' }));
    expect(screen.getByRole('combobox', { name: 'Nama' })).toHaveValue(
        'Budi Santoso',
    );
    expect(screen.getByLabelText('Kegiatan lembur')).toHaveValue(
        'Menyelesaikan pesanan',
    );
});

it('submits the reviewed date only once and disables actions while sending', async () => {
    const user = userEvent.setup();
    const { rerender } = render(<OvertimeForm {...props} />);
    await completeForm(user);
    await openReview(user);

    rerender(<OvertimeForm {...props} dateChoices={[props.dateChoices[0]]} />);
    await user.dblClick(screen.getByRole('button', { name: 'Kirim lembur' }));

    expect(post).toHaveBeenCalledTimes(2);
    expect(post).toHaveBeenLastCalledWith(
        '/lembur',
        expect.objectContaining({
            date_choice: 'YESTERDAY',
            overtime_date: '2026-11-09',
            start_time: '23:00',
            end_time: '02:00',
        }),
        expect.anything(),
    );
    expect(screen.getByRole('button', { name: 'Mengirim…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Ubah' })).toBeDisabled();
});

it('returns to the draft with field errors when the final submit is rejected', async () => {
    const user = userEvent.setup();
    render(<OvertimeForm {...props} />);
    await completeForm(user);
    await openReview(user);

    const message = 'Tanggal lembur sudah berubah. Pilih tanggal lagi.';
    post.mockImplementationOnce(
        (
            _url,
            _data,
            options: {
                onError: (errors: Record<string, string>) => void;
                onFinish: () => void;
            },
        ) => {
            options.onError({ overtime_date: message });
            options.onFinish();
        },
    );
    await user.click(screen.getByRole('button', { name: 'Kirim lembur' }));

    expect(
        screen.getByRole('group', { name: 'Tanggal lembur' }),
    ).toHaveAccessibleDescription(message);
    expect(screen.getByLabelText('Kegiatan lembur')).toHaveValue(
        'Menyelesaikan pesanan',
    );
});
