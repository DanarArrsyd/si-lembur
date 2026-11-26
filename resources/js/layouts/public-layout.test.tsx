import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PublicLayout from '@/layouts/public-layout';

vi.mock('@inertiajs/react', async () =>
    (await import('@/test/inertia-mock')).inertiaMock(),
);

describe('PublicLayout', () => {
    it('shows the wordmark and subtitle without dead links', () => {
        render(
            <PublicLayout>
                <p>Isi form</p>
            </PublicLayout>,
        );

        expect(screen.getByText('SI-LEMBUR')).toBeInTheDocument();
        expect(screen.getByText('Input Lembur · PT Kenco')).toBeInTheDocument();
        // Belum ada halaman cek status (Fase 3): tidak boleh ada tautan mati.
        expect(screen.getByText(/Cek status pengajuan/)).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.getByRole('main')).toHaveTextContent('Isi form');
    });
});
