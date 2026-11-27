import { describe, expect, it } from 'vitest';
import { searchEmployees } from '@/lib/employee-search';
import type { EmployeeOption } from '@/types/public';

const employees: EmployeeOption[] = [
    { id: 1, nik: 'KMI0001', name: 'Budi Santoso' },
    { id: 2, nik: 'KMI0002', name: 'Siti Rahayu' },
    { id: 3, nik: 'KMI0003', name: 'Agus Setiawan' },
    { id: 4, nik: 'KMI0004', name: 'Agus Setiawan' },
    { id: 5, nik: 'KMI0005', name: 'Dewi Lestari' },
    { id: 6, nik: 'KMI0006', name: 'Bambang Susanto' },
];

const names = (query: string) =>
    searchEmployees(employees, query).map((e) => e.name);

describe('searchEmployees', () => {
    // PRD §8 #4
    it.each(['budi', 'santo', 'santosoo', 'BUDI', '  Budi  '])(
        'finds Budi Santoso from "%s" as the first result',
        (query) => {
            expect(names(query)[0]).toBe('Budi Santoso');
        },
    );

    it('matches the start of any word, not the middle', () => {
        expect(names('raha')).toEqual(['Siti Rahayu']);
        expect(names('tari')).not.toContain('Dewi Lestari');
    });

    it('tolerates one or two typos', () => {
        expect(names('lestaro')).toContain('Dewi Lestari');
        expect(names('setiawn')).toContain('Agus Setiawan');
    });

    it('keeps both employees with the same name', () => {
        expect(searchEmployees(employees, 'agus').map((e) => e.id)).toEqual([
            3, 4,
        ]);
    });

    it('returns nothing for a blank query or a name that is not there', () => {
        expect(names('')).toEqual([]);
        expect(names('   ')).toEqual([]);
        expect(names('xyz')).toEqual([]);
        expect(names('wahyudi')).toEqual([]);
    });

    it('does not guess from a single letter', () => {
        expect(names('b')).toEqual(['Bambang Susanto', 'Budi Santoso']);
    });

    it('returns at most 8 results', () => {
        const many = Array.from({ length: 20 }, (_, i) => ({
            id: i + 1,
            nik: `N${i}`,
            name: `Agus ${String.fromCharCode(65 + i)}`,
        }));

        expect(searchEmployees(many, 'agus')).toHaveLength(8);
    });
});
