import { expect, it } from 'vitest';
import { searchEmployees } from './employee-search';

const budi = { id: 1, nik: '001', name: 'Budi Santoso' };
const agus = { id: 2, nik: '002', name: 'Agus Setiawan' };

it.each(['budi', 'santo', 'santosoo', 'santxsox', '  BUDI  '])(
    'finds Budi Santoso for "%s"',
    (query) => {
        expect(searchEmployees([agus, budi], query)).toEqual([budi]);
    },
);

it('prioritizes word prefixes and limits results to eight', () => {
    const similar = Array.from({ length: 9 }, (_, index) => ({
        id: index + 3,
        nik: String(index + 3),
        name: 'Agus Sante',
    }));

    expect(searchEmployees([...similar, budi], 'santo')).toHaveLength(8);
    expect(searchEmployees([...similar, budi], 'santo')[0]).toEqual(budi);
});

it('returns the first eight employees when no query is entered', () => {
    const employees = Array.from({ length: 10 }, (_, id) => ({
        id,
        nik: String(id),
        name: `Karyawan ${id}`,
    }));

    expect(searchEmployees(employees, ' ')).toEqual(employees.slice(0, 8));
    expect(searchEmployees([agus, budi], 'xxzz')).toEqual([]);
});
