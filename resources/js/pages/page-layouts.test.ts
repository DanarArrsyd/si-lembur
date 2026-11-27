import { describe, expect, it } from 'vitest';
import AuthLayout from '@/layouts/auth-layout';
import PanelLayout from '@/layouts/panel-layout';
import PublicLayout from '@/layouts/public-layout';

/**
 * app.tsx tidak lagi memilih layout (supaya kode panel tidak ikut dimuat form publik).
 * Setiap halaman wajib menyatakan layout-nya sendiri; halaman tanpa layout tampil polos.
 */
const pages = import.meta.glob<{ default: { layout?: unknown } }>(
    './**/*.tsx',
    { eager: true },
);

const expected: Record<string, unknown> = {
    'auth/': AuthLayout,
    'panel/': PanelLayout,
    'public/': PublicLayout,
};

describe('page layouts', () => {
    const entries = Object.entries(pages).filter(
        ([path]) => !path.endsWith('.test.tsx'),
    );

    it('finds pages to check', () => {
        expect(entries.length).toBeGreaterThan(0);
    });

    it.each(entries)('%s declares the layout for its area', (path, module) => {
        const area = Object.keys(expected).find((prefix) =>
            path.startsWith(`./${prefix}`),
        );

        expect(
            area,
            `halaman di luar auth/, panel/, public/: ${path}`,
        ).toBeDefined();
        expect(Array.isArray(module.default.layout)).toBe(true);
        expect((module.default.layout as unknown[])[0]).toBe(
            expected[area as string],
        );
    });
});
