import { createInertiaApp } from '@inertiajs/react';

void createInertiaApp({
    // DESIGN.md §8.1: "{Judul halaman} — SI-LEMBUR"
    title: (title) => (title ? `${title} — SI-LEMBUR` : 'SI-LEMBUR'),
    strictMode: true,
    progress: {
        color: '#1e6fd9',
    },
});
