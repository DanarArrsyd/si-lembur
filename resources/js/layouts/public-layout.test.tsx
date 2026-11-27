import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PublicLayout from '@/layouts/public-layout';

vi.mock('@inertiajs/react', async () =>
    (await import('@/test/inertia-mock')).inertiaMock(),
);

describe('PublicLayout', () => {
    it('shows the wordmark, subtitle, and status help link', () => {
        render(
            <PublicLayout>
                <p>Isi form</p>
            </PublicLayout>,
        );

        expect(screen.getByText('SI-LEMBUR')).toBeInTheDocument();
        expect(screen.getByText('Input Lembur · PT Kenco')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Cek status pengajuan' }),
        ).toHaveAttribute('href', '/status');
        expect(screen.getByRole('main')).toHaveTextContent('Isi form');
    });
});
