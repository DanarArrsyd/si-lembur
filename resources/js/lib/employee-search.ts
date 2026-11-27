// Build basic (tanpa extended search): ~7 KB gzip, cukup untuk pencarian fuzzy biasa.
import Fuse from 'fuse.js/basic';
import type { EmployeeOption } from '@/types/public';

/**
 * Pencarian nama untuk typeahead form publik (PRD §4.1, §8 #4).
 *
 * - Tidak peka huruf besar/kecil; spasi di awal/akhir diabaikan.
 * - Setiap kata yang diketik harus cocok dengan AWAL salah satu kata nama
 *   ("santo" → Budi Santoso, "tari" tidak → Dewi Lestari).
 * - Kata ≥ 3 huruf boleh salah ketik 1–2 huruf ("santosoo" → Santoso), lewat fuse.js
 *   yang dijangkar di awal kata (location 0, distance kecil).
 * - Hasil yang cocok persis di awal kata selalu di atas hasil salah ketik.
 */

export const MAX_RESULTS = 8;

/** Kata lebih pendek dari ini hanya dicocokkan persis di awal kata (tanpa toleransi typo). */
const MIN_FUZZY_LENGTH = 3;

type WordEntry = { index: number; word: string };

const fuseCache = new WeakMap<EmployeeOption[], Fuse<WordEntry>>();

function normalize(text: string): string {
    return text.toLocaleLowerCase('id').replace(/\s+/g, ' ').trim();
}

function wordsOf(name: string): string[] {
    return normalize(name).split(' ');
}

function wordIndex(employees: EmployeeOption[]): Fuse<WordEntry> {
    const cached = fuseCache.get(employees);

    if (cached) {
        return cached;
    }

    const entries = employees.flatMap((employee, index) =>
        wordsOf(employee.name).map((word) => ({ index, word })),
    );

    const fuse = new Fuse(entries, {
        keys: ['word'],
        includeScore: true,
        isCaseSensitive: true, // sudah dinormalisasi
        // Dijangkar di awal kata: pergeseran 1 huruf menambah skor 0,5, jadi
        // cocok di tengah kata ("tari" di "lestari") tidak lolos threshold.
        location: 0,
        distance: 2,
        threshold: 0.34,
        minMatchCharLength: MIN_FUZZY_LENGTH,
    });

    fuseCache.set(employees, fuse);

    return fuse;
}

function byName(a: EmployeeOption, b: EmployeeOption): number {
    return a.name.localeCompare(b.name, 'id') || a.nik.localeCompare(b.nik);
}

export function searchEmployees(
    employees: EmployeeOption[],
    query: string,
): EmployeeOption[] {
    const tokens = normalize(query).split(' ').filter(Boolean);

    if (tokens.length === 0) {
        return [];
    }

    const exact: EmployeeOption[] = [];
    const exactIndexes = new Set<number>();

    employees.forEach((employee, index) => {
        const words = wordsOf(employee.name);

        if (tokens.every((t) => words.some((w) => w.startsWith(t)))) {
            exact.push(employee);
            exactIndexes.add(index);
        }
    });

    exact.sort(byName);

    if (
        exact.length >= MAX_RESULTS ||
        tokens.some((t) => t.length < MIN_FUZZY_LENGTH)
    ) {
        return exact.slice(0, MAX_RESULTS);
    }

    // Setiap kata yang diketik harus cocok (persis atau salah ketik) dengan salah satu kata nama.
    const fuse = wordIndex(employees);
    const perToken = tokens.map((token) => {
        const best = new Map<number, number>();

        for (const { item, score = 1 } of fuse.search(token)) {
            best.set(item.index, Math.min(best.get(item.index) ?? 1, score));
        }

        return best;
    });

    // Skor total = jumlah skor terbaik tiap kata; karyawan harus cocok di semua kata.
    const scores: [number, number][] = [...perToken[0].keys()]
        .filter((index) => perToken.every((best) => best.has(index)))
        .map((index) => [
            index,
            perToken.reduce((sum, best) => sum + (best.get(index) ?? 1), 0),
        ]);

    const fuzzy = scores
        .filter(([index]) => !exactIndexes.has(index))
        .sort(
            ([a, sa], [b, sb]) => sa - sb || byName(employees[a], employees[b]),
        )
        .map(([index]) => employees[index]);

    return [...exact, ...fuzzy].slice(0, MAX_RESULTS);
}
