import Fuse from 'fuse.js/basic';

export type Employee = {
    id: number;
    nik: string;
    name: string;
};

const resultLimit = 8;

export function searchEmployees(list: Employee[], query: string): Employee[] {
    const term = query.trim().toLowerCase().replace(/\s+/g, ' ');

    if (!term) {
        return list.slice(0, resultLimit);
    }

    const matches = list.filter((employee) =>
        ` ${employee.name.toLowerCase().replace(/\s+/g, ' ')}`.includes(
            ` ${term}`,
        ),
    );

    if (term.length < 3 || matches.length >= resultLimit) {
        return matches.slice(0, resultLimit);
    }

    const exactIds = new Set(matches.map(({ id }) => id));
    const fuzzy = new Fuse(
        list.filter(({ id }) => !exactIds.has(id)),
        { keys: ['name'], ignoreLocation: true, threshold: 0.35 },
    );

    return [
        ...matches,
        ...fuzzy
            .search(term, { limit: resultLimit - matches.length })
            .map(({ item }) => item),
    ];
}
