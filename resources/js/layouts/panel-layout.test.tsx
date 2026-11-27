import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PanelLayout from '@/layouts/panel-layout';
import { makeUser, pageProps } from '@/test/inertia-mock';

vi.mock('@inertiajs/react', async () =>
    (await import('@/test/inertia-mock')).inertiaMock(),
);

const adminOnly = [
    'Karyawan',
    'Departemen',
    'Pengguna',
    'Kalender libur',
    'Pengaturan',
    'Audit log',
];

function sidebar() {
    return within(screen.getByRole('navigation', { name: 'Menu utama' }));
}

describe('PanelLayout', () => {
    beforeEach(() => {
        pageProps.url = '/panel';
    });

    it('hides admin-only menu for supervisors', () => {
        pageProps.auth.user = makeUser();
        render(<PanelLayout title="Dashboard">x</PanelLayout>);

        for (const label of adminOnly) {
            expect(sidebar().queryByText(label)).not.toBeInTheDocument();
        }
        expect(sidebar().getByText('Persetujuan')).toBeInTheDocument();
    });

    it('shows admin-only menu for admins', () => {
        pageProps.auth.user = makeUser({ role: 'ADMIN', department: null });
        render(<PanelLayout title="Dashboard">x</PanelLayout>);

        for (const label of adminOnly) {
            expect(sidebar().getByText(label)).toBeInTheDocument();
        }
    });

    it('links only to pages that exist and marks the rest as coming soon', () => {
        pageProps.auth.user = makeUser({ role: 'ADMIN', department: null });
        render(<PanelLayout title="Dashboard">x</PanelLayout>);

        const links = sidebar().getAllByRole('link');
        expect(links.map((a) => a.textContent)).toEqual([
            'Dashboard',
            'Persetujuan',
        ]);
        expect(links[0]).toHaveAttribute('aria-current', 'page');

        expect(
            sidebar().getByRole('link', { name: 'Persetujuan' }),
        ).toHaveAttribute('href', '/panel/approvals');
        const history = sidebar().getByText('Riwayat lembur').closest('li');
        expect(history).toHaveAttribute('aria-disabled', 'true');
        expect(history).toHaveTextContent('Segera');
    });

    it('renders the page title, user and logout in the top bar', () => {
        pageProps.auth.user = makeUser();
        render(<PanelLayout title="Dashboard">isi</PanelLayout>);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Dashboard' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Supervisor Produksi')).toBeInTheDocument();
        expect(screen.getByText('Supervisor · Produksi')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Keluar' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Buka menu' }),
        ).toBeInTheDocument();
    });
});
