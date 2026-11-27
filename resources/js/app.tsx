import { createInertiaApp } from '@inertiajs/react';

/**
 * Layout dan provider khusus panel (sidebar, toast, tooltip) dideklarasikan di tiap halaman
 * (`Page.layout = [Layout, props]`), bukan di sini. Dengan begitu bundle awal form publik
 * hanya berisi React + Inertia, dan kode panel dimuat saat halaman panel dibuka
 * (docs/ARCHITECTURE.md §6).
 */
void createInertiaApp({
    // DESIGN.md §8.1: "{Judul halaman} — SI-LEMBUR"
    title: (title) => (title ? `${title} — SI-LEMBUR` : 'SI-LEMBUR'),
    strictMode: true,
    progress: {
        color: '#1e6fd9',
    },
});
