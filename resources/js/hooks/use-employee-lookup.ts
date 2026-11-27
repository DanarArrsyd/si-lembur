import { useEffect, useRef, useState } from 'react';
import type { EmployeeOption } from '@/types/public';

type LookupResult = {
    department: number;
    employees: EmployeeOption[];
    error?: string;
};

export function useEmployeeLookup(department: number | null) {
    const [result, setResult] = useState<LookupResult | null>(null);
    const [attempt, setAttempt] = useState(0);
    const cache = useRef(new Map<number, EmployeeOption[]>());

    useEffect(() => {
        if (department === null) return;
        const cached = cache.current.get(department);
        if (cached) {
            setResult({ department, employees: cached });
            return;
        }

        const controller = new AbortController();
        setResult(null);

        void (async () => {
            try {
                const response = await fetch(
                    `/api/departments/${department}/employees`,
                    { signal: controller.signal },
                );
                if (!response.ok) throw new Error('Employee lookup failed');
                const employees = (await response.json()) as EmployeeOption[];
                if (!controller.signal.aborted) {
                    cache.current.set(department, employees);
                    setResult({ department, employees });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setResult({
                        department,
                        employees: [],
                        error: 'Daftar nama gagal dimuat.',
                    });
                }
            }
        })();

        return () => controller.abort();
    }, [department, attempt]);

    const current = result?.department === department ? result : null;

    return {
        employees: current?.employees ?? [],
        loading: department !== null && current === null,
        error: current?.error,
        reload: () => {
            setResult(null);
            setAttempt((count) => count + 1);
        },
    };
}
